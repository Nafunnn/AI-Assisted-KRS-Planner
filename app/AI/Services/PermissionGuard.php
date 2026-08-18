<?php

namespace App\AI\Services;

use App\AI\Registry\EntityDefinition;
use App\AI\Registry\ModuleRegistry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PermissionGuard
{
    public function __construct(
        protected ModuleRegistry $registry,
    ) {}

    public function canAccessEntity(User $user, string $entityKey, ?string $action = null): bool
    {
        $entity = $this->registry->get($entityKey);

        if (! $entity) {
            return false;
        }

        return $this->canAccessDefinition($user, $entity, $action);
    }

    public function canAccessDefinition(User $user, EntityDefinition $entity, ?string $action = null): bool
    {
        if (! $entity->policy) {
            return false;
        }

        $ability = $this->resolveAbility($entity, $action);

        return $this->checkPolicyAbility($user, $entity, $ability);
    }

    public function canAccessModel(User $user, EntityDefinition $entity, Model $model, string $action): bool
    {
        if (! $entity->policy) {
            return false;
        }

        $ability = $entity->policyAbilities[$action] ?? $action;

        return $this->invokePolicy($user, $entity, $ability, $model);
    }

    public function canUseAiAssistant(User $user): bool
    {
        return $user->aiProviderConfigs()->where('is_active', true)->exists();
    }

    /**
     * @return list<string>
     */
    public function allowedEntityKeys(User $user): array
    {
        $allowed = [];

        foreach ($this->registry->all() as $key => $entity) {
            if ($this->canAccessDefinition($user, $entity)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * @return array<string, string>
     */
    public function allowedRelationsForDefinition(User $user, EntityDefinition $entity): array
    {
        $allowed = [];

        foreach ($entity->relations as $relationName => $relatedEntityKey) {
            if ($this->canAccessEntity($user, $relatedEntityKey)) {
                $allowed[$relationName] = $relatedEntityKey;
            }
        }

        return $allowed;
    }

    /**
     * @return list<string>
     */
    public function allowedMutationEntityKeys(User $user): array
    {
        $allowed = [];

        foreach ($this->registry->all() as $key => $entity) {
            if ($this->canMutateEntity($user, $entity)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * @return list<string>
     */
    public function allowedDeleteEntityKeys(User $user): array
    {
        $allowed = [];

        foreach ($this->registry->all() as $key => $entity) {
            if ($this->canAccessDefinition($user, $entity, 'delete')) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    /**
     * @return list<string>
     */
    public function allowedActionsForDefinition(User $user, EntityDefinition $entity): array
    {
        if (! $entity->policy) {
            return [];
        }

        $allowed = [];

        foreach ($entity->policyAbilities as $action => $ability) {
            if ($this->checkPolicyAbility($user, $entity, $ability)) {
                $allowed[] = $action;
            }
        }

        return $allowed;
    }

    public function capabilitiesSummary(User $user): string
    {
        $queryKeys = $this->allowedEntityKeys($user);
        $mutationKeys = $this->allowedMutationEntityKeys($user);
        $deleteKeys = $this->allowedDeleteEntityKeys($user);

        $parts = [];

        if ($queryKeys === []) {
            $parts[] = 'Anda tidak memiliki akses query ke entity manapun.';
        } else {
            $parts[] = 'Anda dapat query: '.implode(', ', $queryKeys).'.';
        }

        if ($mutationKeys === []) {
            $parts[] = 'Anda tidak dapat create/update via AI.';
        } else {
            $parts[] = 'Anda dapat create/update: '.implode(', ', $mutationKeys).'.';
        }

        if ($deleteKeys === []) {
            $parts[] = 'Anda tidak dapat delete via AI.';
        } else {
            $parts[] = 'Anda dapat delete: '.implode(', ', $deleteKeys).'.';
        }

        $parts[] = 'Gunakan tool domain KRS untuk review bentrok, saran kelompok, sync jadwal, dan generate jadwal otomatis.';

        return implode(' ', $parts);
    }

    protected function canMutateEntity(User $user, EntityDefinition $entity): bool
    {
        return $this->canAccessDefinition($user, $entity, 'create')
            || $this->canAccessDefinition($user, $entity, 'update');
    }

    protected function resolveAbility(EntityDefinition $entity, ?string $action): string
    {
        $action ??= $entity->permissionAction;

        return $entity->policyAbilities[$action] ?? $action;
    }

    protected function checkPolicyAbility(User $user, EntityDefinition $entity, string $ability): bool
    {
        return $this->invokePolicy($user, $entity, $ability);
    }

    protected function invokePolicy(User $user, EntityDefinition $entity, string $ability, ?Model $model = null): bool
    {
        $policy = app($entity->policy);

        if (! method_exists($policy, $ability)) {
            return false;
        }

        if (in_array($ability, ['viewAny', 'create'], true)) {
            return (bool) $policy->{$ability}($user);
        }

        if ($model === null) {
            return (bool) $policy->viewAny($user);
        }

        return (bool) $policy->{$ability}($user, $model);
    }
}
