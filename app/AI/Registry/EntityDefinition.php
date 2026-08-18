<?php

namespace App\AI\Registry;

class EntityDefinition
{
    /**
     * @param  list<string>  $searchable
     * @param  list<string>  $filterable
     * @param  list<string>  $sortable
     * @param  list<string>  $aggregates
     * @param  array<string, string>  $relations
     * @param  list<string>  $hidden
     * @param  array<string, string>  $policyAbilities
     * @param  array<string, array{route: string, param?: string, label?: string}>  $adminRoutes
     * @param  array<string, string>  $adminLocation
     * @param  array<string, array<string, mixed>>  $fields
     * @param  list<string>  $businessRules
     * @param  list<string>  $queryHints
     * @param  array<string, string>  $computed
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $model,
        public readonly string $description,
        public readonly ?string $policy,
        public readonly array $searchable = [],
        public readonly array $filterable = [],
        public readonly array $sortable = [],
        public readonly array $aggregates = ['count'],
        public readonly array $relations = [],
        public readonly array $hidden = [],
        public readonly string $permissionAction = 'list',
        public readonly array $policyAbilities = [],
        public readonly ?string $scope = null,
        public readonly ?string $scopeField = null,
        public readonly array $adminRoutes = [],
        public readonly array $adminLocation = [],
        public readonly array $fields = [],
        public readonly array $businessRules = [],
        public readonly array $queryHints = [],
        public readonly array $computed = [],
    ) {}

    public static function fromArray(string $key, array $data): self
    {
        return new self(
            key: $key,
            name: $data['name'] ?? $key,
            model: $data['model'],
            description: $data['description'] ?? '',
            policy: $data['policy'] ?? null,
            searchable: $data['searchable'] ?? [],
            filterable: $data['filterable'] ?? [],
            sortable: $data['sortable'] ?? [],
            aggregates: $data['aggregates'] ?? ['count'],
            relations: $data['relations'] ?? [],
            hidden: $data['hidden'] ?? [],
            permissionAction: $data['permission_action'] ?? 'list',
            policyAbilities: $data['policy_abilities'] ?? [
                'list' => 'viewAny',
                'show' => 'view',
                'create' => 'create',
                'update' => 'update',
                'delete' => 'delete',
            ],
            scope: $data['scope'] ?? null,
            scopeField: $data['scope_field'] ?? null,
            adminRoutes: $data['admin_routes'] ?? [],
            adminLocation: $data['admin_location'] ?? [],
            fields: $data['fields'] ?? [],
            businessRules: $data['business_rules'] ?? [],
            queryHints: $data['query_hints'] ?? [],
            computed: $data['computed'] ?? [],
        );
    }

    /**
     * @param  array<string, string>|null  $relationsOverride
     */
    public function toPromptArray(?array $relationsOverride = null): array
    {
        $payload = [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'searchable' => $this->searchable,
            'filterable' => $this->filterable,
            'sortable' => $this->sortable,
            'aggregates' => $this->aggregates,
            'relations' => $relationsOverride ?? $this->relations,
        ];

        if ($this->fields !== []) {
            $payload['fields'] = $this->fields;
        }

        if ($this->businessRules !== []) {
            $payload['business_rules'] = $this->businessRules;
        }

        if ($this->queryHints !== []) {
            $payload['query_hints'] = $this->queryHints;
        }

        if ($this->computed !== []) {
            $payload['computed'] = $this->computed;
        }

        return $payload;
    }
}
