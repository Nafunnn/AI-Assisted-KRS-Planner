<?php

namespace App\AI\DTO;

class EntityQueryDTO
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $with
     */
    public function __construct(
        public readonly string $entity,
        public readonly array $filters = [],
        public readonly ?string $search = null,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'asc',
        public readonly int $limit = 20,
        public readonly int $offset = 0,
        public readonly array $with = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $limit = (int) ($data['limit'] ?? config('ai-platform.query.default_limit', 20));
        $max = (int) config('ai-platform.query.max_limit', 100);

        return new self(
            entity: (string) ($data['entity'] ?? ''),
            filters: is_array($data['filters'] ?? null) ? $data['filters'] : [],
            search: isset($data['search']) ? (string) $data['search'] : null,
            sortBy: isset($data['sort_by']) ? (string) $data['sort_by'] : null,
            sortDir: strtolower((string) ($data['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            limit: max(1, min($limit, $max)),
            offset: max(0, (int) ($data['offset'] ?? 0)),
            with: is_array($data['with'] ?? null) ? array_values($data['with']) : [],
        );
    }
}
