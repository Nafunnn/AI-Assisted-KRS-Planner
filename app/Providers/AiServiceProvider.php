<?php

namespace App\Providers;

use App\AI\Contracts\EntityRegistryInterface;
use App\AI\Registry\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntityRegistryInterface::class, ModuleRegistry::class);
        $this->app->singleton(ModuleRegistry::class);
    }
}
