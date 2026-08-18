<?php

namespace App\AI\DTO;

class DeleteEntityDTO
{
    public function __construct(
        public readonly string $entity,
        public readonly string $id,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            entity: (string) ($data['entity'] ?? ''),
            id: (string) ($data['id'] ?? ''),
        );
    }
}
