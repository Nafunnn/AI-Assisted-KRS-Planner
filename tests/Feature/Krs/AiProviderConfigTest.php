<?php

use App\Models\AiProviderConfig;
use App\Models\KrsPlan;
use App\Models\User;

test('user can manage ai provider configs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ai-providers.edit'))
        ->assertOk();

    $this->actingAs($user)
        ->post(route('ai-providers.store'), [
            'provider' => 'openai',
            'name' => 'OpenAI Dev',
            'api_key' => 'sk-test',
            'default_model' => 'gpt-4o-mini',
            'is_active' => true,
        ])
        ->assertRedirect(route('ai-providers.edit'));

    $config = AiProviderConfig::query()->where('user_id', $user->id)->first();

    expect($config)->not->toBeNull()
        ->and($config->is_active)->toBeTrue();
});

test('ai auto schedule returns planned status without active provider', function () {
    $user = User::factory()->create();
    $plan = KrsPlan::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('krs.plans.ai.auto-schedule', $plan), [
            'free_days' => ['friday'],
        ])
        ->assertSuccessful()
        ->assertJson(['status' => 'unavailable']);
});
