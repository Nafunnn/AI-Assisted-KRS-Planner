<?php

namespace App\AI\Support;

use App\AI\Registry\EntityDefinition;
use Illuminate\Support\Facades\File;

class ModuleScanner
{
    /**
     * @param  list<string>  $paths
     * @return array<string, EntityDefinition>
     */
    public function scan(array $paths): array
    {
        $entities = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (File::directories($path) as $directory) {
                $entityFile = $directory.DIRECTORY_SEPARATOR.'entity.php';

                if (! File::exists($entityFile)) {
                    continue;
                }

                /** @var array $definition */
                $definition = require $entityFile;
                $key = $definition['key'] ?? strtolower(basename($directory));

                $entities[$key] = EntityDefinition::fromArray($key, $definition);
            }
        }

        return $entities;
    }
}
