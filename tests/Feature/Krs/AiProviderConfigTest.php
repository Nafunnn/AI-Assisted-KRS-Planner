<?php

use App\AI\Services\AiProviderTestService;
use App\Enums\AiProvider;
use App\Models\AiProviderConfig;
use App\Models\User;

test('user can manage ai provider configs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ai-providers.edit'))
        ->assertOk();

    $this->actingAs($user)
        ->post(route('ai-providers.store'), [
            'provider' => '9router',
            'name' => '9Router Dev',
            'api_key' => '9router',
            'default_model' => 'claude-sonnet-4',
            'is_active' => true,
        ])
        ->assertRedirect(route('ai-providers.edit'));

    $config = AiProviderConfig::query()->where('user_id', $user->id)->first();

    expect($config)->not->toBeNull()
        ->and($config->is_active)->toBeTrue();
});

test('ai chat returns unavailable without active provider', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), [
            'message' => 'Hello',
        ])
        ->assertSuccessful()
        ->assertJson(['status' => 'unavailable']);
});

test('user can test saved ai provider config', function () {
    $user = User::factory()->create();
    $config = AiProviderConfig::factory()->for($user)->create();

    $this->mock(AiProviderTestService::class, function ($mock): void {
        $mock->shouldReceive('test')
            ->once()
            ->andReturn([
                'status' => 'ok',
                'message' => 'Koneksi berhasil.',
                'reply' => 'OK',
                'provider_label' => '9Router (Local)',
                'latency_ms' => 120,
            ]);
    });

    $this->actingAs($user)
        ->postJson(route('ai-providers.test-saved', $config))
        ->assertOk()
        ->assertJson(['status' => 'ok', 'reply' => 'OK']);
});

test('user cannot test another users ai provider config', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $config = AiProviderConfig::factory()->for($owner)->create();

    $this->actingAs($other)
        ->postJson(route('ai-providers.test-saved', $config))
        ->assertForbidden();
});

test('user can test draft ai provider config', function () {
    $user = User::factory()->create();

    $this->mock(AiProviderTestService::class, function ($mock): void {
        $mock->shouldReceive('configFromInput')
            ->once()
            ->andReturn(new AiProviderConfig([
                'provider' => AiProvider::NineRouter,
            ]));

        $mock->shouldReceive('test')
            ->once()
            ->andReturn([
                'status' => 'ok',
                'message' => 'Koneksi berhasil.',
            ]);
    });

    $this->actingAs($user)
        ->postJson(route('ai-providers.test'), [
            'provider' => '9router',
            'default_model' => 'claude-sonnet-4',
        ])
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

test('custom provider test requires base url', function () {
    $tester = app(AiProviderTestService::class);

    $result = $tester->test(new AiProviderConfig([
        'provider' => AiProvider::Custom,
        'base_url' => null,
    ]));

    expect($result['status'])->toBe('error')
        ->and($result['message'])->toContain('Base URL');
});
