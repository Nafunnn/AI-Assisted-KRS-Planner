<?php

namespace App\AI\DTO;

class UpdateEntityDTO
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $entity,
        public readonly string $id,
        public readonly array $data = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            entity: (string) ($data['entity'] ?? ''),
            id: (string) ($data['id'] ?? ''),
            data: is_array($data['data'] ?? null) ? $data['data'] : [],
        );
    }
}
