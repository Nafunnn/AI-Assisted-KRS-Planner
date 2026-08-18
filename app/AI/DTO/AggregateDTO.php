<?php

namespace App\AI\DTO;

class AggregateDTO
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly string $entity,
        public readonly string $aggregate,
        public readonly ?string $field = null,
        public readonly array $filters = [],
        public readonly ?string $groupBy = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            entity: (string) ($data['entity'] ?? ''),
            aggregate: strtolower((string) ($data['aggregate'] ?? 'count')),
            field: isset($data['field']) ? (string) $data['field'] : null,
            filters: is_array($data['filters'] ?? null) ? $data['filters'] : [],
            groupBy: isset($data['group_by']) ? (string) $data['group_by'] : null,
        );
    }
}
