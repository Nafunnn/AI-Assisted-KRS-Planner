<?php

namespace App\AI\DTO;

class CreateEntityDTO
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $entity,
        public readonly array $data = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            entity: (string) ($data['entity'] ?? ''),
            data: is_array($data['data'] ?? null) ? $data['data'] : [],
        );
    }
}
