<?php

namespace App\AI\Services;

use App\AI\DTO\CreateEntityDTO;
use App\AI\DTO\DeleteEntityDTO;
use App\AI\DTO\UpdateEntityDTO;
use App\AI\Exceptions\EntityNotFoundException;
use App\AI\Exceptions\UnauthorizedEntityAccessException;
use App\AI\Registry\EntityDefinition;
use App\AI\Registry\ModuleRegistry;
use App\Models\KrsPlan;
use App\Models\KrsPlanItem;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class EntityMutationService
{
    public function __construct(
        protected ModuleRegistry $registry,
        protected PermissionGuard $permissionGuard,
        protected EntityAdminLinkResolver $linkResolver,
    ) {}

    public function create(User $user, CreateEntityDTO $dto): array
    {
        $entity = $this->resolveEntity($user, $dto->entity);

        if (! $this->canCreateEntity($user, $entity)) {
            throw UnauthorizedEntityAccessException::forKey($entity->key);
        }

        $result = match ($entity->key) {
            'krs_plan_item' => $this->createPlanItem($user, $dto->data),
            default => [
                'error' => "Create not implemented for entity [{$entity->key}].",
            ],
        };

        return $this->linkResolver->enrichMutationResponse($user, $entity, $result);
    }

    public function update(User $user, UpdateEntityDTO $dto): array
    {
        $entity = $this->resolveEntity($user, $dto->entity);

        if (! $this->permissionGuard->canAccessDefinition($user, $entity, 'update')) {
            throw UnauthorizedEntityAccessException::forKey($entity->key);
        }

        $result = match ($entity->key) {
            'krs_plan' => $this->updateKrsPlan($user, $dto->id, $dto->data),
            default => [
                'error' => "Update not implemented for entity [{$entity->key}].",
            ],
        };

        return $this->linkResolver->enrichMutationResponse($user, $entity, $result);
    }

    public function delete(User $user, DeleteEntityDTO $dto): array
    {
        $entity = $this->resolveEntity($user, $dto->entity, forDelete: true);

        if (! $this->permissionGuard->canAccessDefinition($user, $entity, 'delete')) {
            throw UnauthorizedEntityAccessException::forKey($entity->key);
        }

        $result = match ($entity->key) {
            'krs_plan_item' => $this->deletePlanItem($user, $dto->id),
            default => [
                'error' => "Delete not implemented for entity [{$entity->key}].",
            ],
        };

        return $this->linkResolver->enrichMutationResponse($user, $entity, $result);
    }

    protected function resolveEntity(User $user, string $key, bool $forDelete = false): EntityDefinition
    {
        $entity = $this->registry->resolve($key);

        if (! $entity) {
            $available = $forDelete
                ? $this->permissionGuard->allowedDeleteEntityKeys($user)
                : $this->permissionGuard->allowedMutationEntityKeys($user);

            throw EntityNotFoundException::forKey($key, $available);
        }

        return $entity;
    }

    protected function canCreateEntity(User $user, EntityDefinition $entity): bool
    {
        return $this->permissionGuard->canAccessDefinition($user, $entity, 'create');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function createPlanItem(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'krs_plan_id' => ['required', 'integer', 'exists:krs_plans,id'],
            'course_section_id' => ['required', 'integer', 'exists:course_sections,id'],
        ]);

        if ($validator->fails()) {
            return [
                'error' => 'validation_failed',
                'details' => $validator->errors()->toArray(),
            ];
        }

        $plan = KrsPlan::query()->find($data['krs_plan_id']);

        if (! $plan || ! $user->can('update', $plan)) {
            return ['error' => 'unauthorized', 'message' => 'Tidak dapat menambah item ke rencana ini.'];
        }

        $item = $plan->items()->create([
            'course_section_id' => $data['course_section_id'],
        ]);

        return [
            'entity' => 'krs_plan_item',
            'created' => true,
            'id' => $item->id,
            'krs_plan_id' => $plan->id,
            'course_section_id' => $item->course_section_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function updateKrsPlan(User $user, string $id, array $data): array
    {
        $validator = Validator::make($data, [
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return [
                'error' => 'validation_failed',
                'details' => $validator->errors()->toArray(),
            ];
        }

        $plan = KrsPlan::query()->find($id);

        if (! $plan) {
            return ['error' => 'krs_plan_not_found', 'id' => $id];
        }

        if (! $user->can('update', $plan)) {
            return ['error' => 'unauthorized'];
        }

        if (isset($data['name'])) {
            $plan->update(['name' => $data['name']]);
        }

        return [
            'entity' => 'krs_plan',
            'updated' => true,
            'id' => $plan->id,
            'name' => $plan->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function deletePlanItem(User $user, string $id): array
    {
        $item = KrsPlanItem::query()->with('krsPlan')->find($id);

        if (! $item) {
            return ['error' => 'krs_plan_item_not_found', 'id' => $id];
        }

        if (! $user->can('update', $item->krsPlan)) {
            return ['error' => 'unauthorized'];
        }

        $snapshot = [
            'id' => $item->id,
            'krs_plan_id' => $item->krs_plan_id,
            'course_section_id' => $item->course_section_id,
        ];

        $item->delete();

        return [
            'entity' => 'krs_plan_item',
            'deleted' => true,
            ...$snapshot,
        ];
    }
}
