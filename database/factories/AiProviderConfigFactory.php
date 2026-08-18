<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiProviderConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProviderConfig>
 */
class AiProviderConfigFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => AiProvider::NineRouter,
            'name' => '9Router Default',
            'base_url' => null,
            'api_key' => 'sk-test-key',
            'default_model' => 'gpt-4o-mini',
            'is_active' => false,
        ];
    }
}
