<?php

namespace App\AI\Services;

use App\AI\DTO\AggregateDTO;
use App\AI\DTO\EntityQueryDTO;
use App\AI\Exceptions\EntityNotFoundException;
use App\AI\Exceptions\UnauthorizedEntityAccessException;
use App\AI\Registry\EntityDefinition;
use App\AI\Registry\ModuleRegistry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EntityQueryService
{
    public function __construct(
        protected ModuleRegistry $registry,
        protected PermissionGuard $permissionGuard,
        protected EntityAdminLinkResolver $linkResolver,
    ) {}

    public function query(User $user, EntityQueryDTO $dto): array
    {
        $entity = $this->resolveAuthorizedEntity($user, $dto->entity);
        $query = $this->baseQuery($entity, $user);

        $this->applyFilters($query, $entity, $dto->filters);
        $this->applySearch($query, $entity, $dto->search);
        $this->applySort($query, $entity, $dto->sortBy, $dto->sortDir);
        $this->applyRelations($query, $entity, $dto->with, $user);

        $total = (clone $query)->count();

        $rows = $query
            ->skip($dto->offset)
            ->take($dto->limit)
            ->get()
            ->map(fn (Model $model) => $this->sanitize($model, $entity, $user))
            ->values()
            ->all();

        return [
            'entity' => $entity->key,
            'total' => $total,
            'limit' => $dto->limit,
            'offset' => $dto->offset,
            'data' => $rows,
        ];
    }

    public function search(User $user, string $entityKey, string $term, int $limit = 20): array
    {
        $dto = EntityQueryDTO::fromArray([
            'entity' => $entityKey,
            'search' => $term,
            'limit' => $limit,
        ]);

        return $this->query($user, $dto);
    }

    public function aggregate(User $user, AggregateDTO $dto): array
    {
        $entity = $this->resolveAuthorizedEntity($user, $dto->entity);

        if (! in_array($dto->aggregate, $entity->aggregates, true)) {
            return [
                'error' => "Aggregate [{$dto->aggregate}] is not allowed for entity [{$entity->key}].",
                'allowed' => $entity->aggregates,
            ];
        }

        $query = $this->baseQuery($entity, $user);
        $this->applyFilters($query, $entity, $dto->filters);

        if ($dto->groupBy) {
            if (! in_array($dto->groupBy, $entity->filterable, true) && ! in_array($dto->groupBy, $entity->sortable, true)) {
                return ['error' => "Group by field [{$dto->groupBy}] is not allowed."];
            }

            $selectRaw = match ($dto->aggregate) {
                'sum' => 'sum('.$this->safeColumn($dto->field ?? $dto->groupBy).') as value',
                'avg' => 'avg('.$this->safeColumn($dto->field ?? $dto->groupBy).') as value',
                'min' => 'min('.$this->safeColumn($dto->field ?? $dto->groupBy).') as value',
                'max' => 'max('.$this->safeColumn($dto->field ?? $dto->groupBy).') as value',
                default => 'count(*) as value',
            };

            $groupColumn = $this->safeColumn($dto->groupBy);
            $rows = $query
                ->selectRaw($groupColumn.' as group_key, '.$selectRaw)
                ->groupBy($groupColumn)
                ->orderBy('group_key')
                ->limit(100)
                ->get()
                ->map(fn ($row) => [
                    'group' => $row->group_key,
                    'value' => is_numeric($row->value) ? 0 + $row->value : $row->value,
                ])
                ->all();

            return [
                'entity' => $entity->key,
                'aggregate' => $dto->aggregate,
                'group_by' => $dto->groupBy,
                'data' => $rows,
            ];
        }

        $value = match ($dto->aggregate) {
            'sum' => $query->sum($this->assertAggregatableField($entity, $dto->field)),
            'avg' => $query->avg($this->assertAggregatableField($entity, $dto->field)),
            'min' => $query->min($this->assertAggregatableField($entity, $dto->field)),
            'max' => $query->max($this->assertAggregatableField($entity, $dto->field)),
            default => $query->count(),
        };

        return [
            'entity' => $entity->key,
            'aggregate' => $dto->aggregate,
            'field' => $dto->field,
            'value' => is_numeric($value) ? 0 + $value : $value,
        ];
    }

    /**
     * @param  list<string>  $with
     * @return array<string, mixed>
     */
    public function findById(User $user, string $entityKey, string $id, array $with = []): array
    {
        $entity = $this->resolveAuthorizedEntity($user, $entityKey);

        $query = $this->baseQuery($entity, $user)->where('id', $id);
        $this->applyRelations($query, $entity, $with, $user);

        /** @var Model|null $model */
        $model = $query->first();

        if (! $model) {
            throw EntityNotFoundException::forKey($entityKey, $this->permissionGuard->allowedEntityKeys($user));
        }

        if (! $this->permissionGuard->canAccessModel($user, $entity, $model, 'show')) {
            throw UnauthorizedEntityAccessException::forKey($entity->key);
        }

        $data = $this->sanitize($model, $entity, $user);

        $result = [
            'entity' => $entity->key,
            'data' => $data,
        ];

        $location = $this->linkResolver->location($entity, 'show');

        if ($location !== null) {
            $result['_location'] = $location;
        }

        return $result;
    }

    protected function resolveAuthorizedEntity(User $user, string $key): EntityDefinition
    {
        $entity = $this->registry->resolve($key);

        if (! $entity) {
            throw EntityNotFoundException::forKey($key, $this->permissionGuard->allowedEntityKeys($user));
        }

        if (! $this->permissionGuard->canAccessDefinition($user, $entity)) {
            throw UnauthorizedEntityAccessException::forKey($entity->key);
        }

        return $entity;
    }

    protected function baseQuery(EntityDefinition $entity, User $user): Builder
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $entity->model;

        $query = $modelClass::query();

        $this->applyOwnershipScope($query, $entity, $user);

        return $query;
    }

    protected function applyOwnershipScope(Builder $query, EntityDefinition $entity, User $user): void
    {
        match ($entity->scope) {
            'owner' => $query->where($entity->scopeField ?? 'user_id', $user->id),
            'published_catalog' => $user->isAdmin()
                ? null
                : $query->whereNotNull('published_at'),
            'published_offering' => $user->isAdmin()
                ? null
                : $query->whereHas('courseOffering', fn (Builder $q) => $q->whereNotNull('published_at')),
            'published_course' => $user->isAdmin()
                ? null
                : $query->whereHas('course.courseOffering', fn (Builder $q) => $q->whereNotNull('published_at')),
            'published_section' => $user->isAdmin()
                ? null
                : $query->whereHas('courseSection.course.courseOffering', fn (Builder $q) => $q->whereNotNull('published_at')),
            'plan_owner' => $query->whereHas('krsPlan', fn (Builder $q) => $q->where('user_id', $user->id)),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, EntityDefinition $entity, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if (! in_array($field, $entity->filterable, true)) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }
    }

    protected function applySearch(Builder $query, EntityDefinition $entity, ?string $term): void
    {
        if (! filled($term) || $entity->searchable === []) {
            return;
        }

        $query->where(function (Builder $q) use ($entity, $term) {
            foreach ($entity->searchable as $index => $field) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $q->{$method}($field, 'like', '%'.$term.'%');
            }
        });
    }

    protected function applySort(Builder $query, EntityDefinition $entity, ?string $sortBy, string $sortDir): void
    {
        if (! $sortBy || ! in_array($sortBy, $entity->sortable, true)) {
            return;
        }

        $query->orderBy($sortBy, $sortDir);
    }

    /**
     * @param  list<string>  $with
     */
    protected function applyRelations(Builder $query, EntityDefinition $entity, array $with, User $user): void
    {
        $allowedRelations = $this->permissionGuard->allowedRelationsForDefinition($user, $entity);
        $allowedNames = array_keys($allowedRelations);
        $requested = array_values(array_intersect($with, array_keys($entity->relations), $allowedNames));

        if ($requested !== []) {
            $query->with($requested);
        }
    }

    protected function sanitize(Model $model, EntityDefinition $entity, User $user): array
    {
        $data = $model->toArray();

        foreach ($entity->hidden as $field) {
            unset($data[$field]);
        }

        $allowedRelations = $this->permissionGuard->allowedRelationsForDefinition($user, $entity);

        foreach ($entity->relations as $relationName => $relatedEntityKey) {
            if (! array_key_exists($relationName, $data)) {
                continue;
            }

            if (! isset($allowedRelations[$relationName])) {
                unset($data[$relationName]);

                continue;
            }

            $relatedEntity = $this->registry->get($relatedEntityKey);
            if (! $relatedEntity) {
                unset($data[$relationName]);

                continue;
            }

            $related = $data[$relationName];

            if ($related === null) {
                continue;
            }

            if ($this->isAssocArray($related)) {
                $data[$relationName] = $this->sanitizeRelatedArray($related, $relatedEntity);

                continue;
            }

            if (is_array($related)) {
                $data[$relationName] = array_map(
                    fn ($row) => is_array($row) ? $this->sanitizeRelatedArray($row, $relatedEntity) : $row,
                    $related
                );
            }
        }

        return $this->linkResolver->enrichRecord($user, $entity, $data);
    }

    /**
     * @param  array<string, mixed>  $related
     * @return array<string, mixed>
     */
    protected function sanitizeRelatedArray(array $related, EntityDefinition $relatedEntity): array
    {
        foreach ($relatedEntity->hidden as $field) {
            unset($related[$field]);
        }

        return $related;
    }

    /**
     * @param  array<mixed>  $array
     */
    protected function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function assertAggregatableField(EntityDefinition $entity, ?string $field): string
    {
        if (! $field) {
            throw new \InvalidArgumentException('Field is required for this aggregate.');
        }

        if (! in_array($field, $entity->filterable, true) && ! in_array($field, $entity->sortable, true)) {
            throw new \InvalidArgumentException("Field [{$field}] is not allowed for aggregation.");
        }

        return $this->safeColumn($field);
    }

    protected function safeColumn(string $column): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException('Invalid column name.');
        }

        return $column;
    }
}
