<?php

namespace App\AI\Registry;

use App\AI\Contracts\EntityRegistryInterface;
use App\AI\Support\ModuleScanner;
use Illuminate\Support\Str;

class ModuleRegistry implements EntityRegistryInterface
{
    /** @var array<string, EntityDefinition> */
    protected array $entities = [];

    protected bool $booted = false;

    public function __construct(
        protected ModuleScanner $scanner,
    ) {}

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $paths = config('ai-platform.module_paths', [app_path('AI/Modules')]);
        $this->entities = $this->scanner->scan($paths);
        $this->booted = true;
    }

    public function all(): array
    {
        $this->boot();

        return $this->entities;
    }

    public function get(string $key): ?EntityDefinition
    {
        $this->boot();

        return $this->entities[$key] ?? null;
    }

    /**
     * Resolve an entity key with tolerant matching (case, plural/singular, name).
     */
    public function resolve(string $key): ?EntityDefinition
    {
        $this->boot();

        $normalized = Str::of($key)->trim()->lower()->replace([' ', '-'], '_')->toString();

        if ($normalized === '') {
            return null;
        }

        if (isset($this->entities[$normalized])) {
            return $this->entities[$normalized];
        }

        $candidates = array_values(array_unique(array_filter([
            $normalized,
            Str::singular($normalized),
            Str::plural($normalized),
            str_replace('_', '', $normalized),
        ])));

        foreach ($candidates as $candidate) {
            if (isset($this->entities[$candidate])) {
                return $this->entities[$candidate];
            }
        }

        foreach ($this->entities as $entity) {
            $name = Str::lower($entity->name);
            if ($name === $normalized || Str::singular($name) === $normalized || Str::plural($name) === $normalized) {
                return $entity;
            }
        }

        return null;
    }

    public function has(string $key): bool
    {
        return $this->resolve($key) !== null;
    }

    public function keys(): array
    {
        return array_keys($this->all());
    }
}
