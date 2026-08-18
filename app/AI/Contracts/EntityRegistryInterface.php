<?php

namespace App\AI\Contracts;

use App\AI\Registry\EntityDefinition;

interface EntityRegistryInterface
{
    /**
     * @return array<string, EntityDefinition>
     */
    public function all(): array;

    public function get(string $key): ?EntityDefinition;

    public function has(string $key): bool;

    /**
     * @return list<string>
     */
    public function keys(): array;
}
