<?php

namespace App\AI\Services;

use App\AI\DTO\AiAssistantSettings;
use App\Enums\AiProvider;
use App\Models\AiProviderConfig;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class AiConfigResolver
{
    /** @var list<string> */
    private const OPENAI_COMPATIBLE_GATEWAYS = ['9router', 'custom-gateway'];

    public function forUser(User $user): ?AiProviderConfig
    {
        return $user->aiProviderConfigs()->where('is_active', true)->first();
    }

    public function settingsForUser(User $user): ?AiAssistantSettings
    {
        $config = $this->forUser($user);

        if ($config === null) {
            return null;
        }

        return new AiAssistantSettings(
            isEnabled: true,
            provider: $this->mapProvider($config),
            model: $config->default_model,
            systemPersona: config('ai-platform.persona'),
        );
    }

    /**
     * Apply the user's active AI provider config onto Laravel AI config.
     */
    public function apply(User $user): AiAssistantSettings
    {
        $config = $this->forUser($user);

        if ($config === null) {
            throw new RuntimeException('Fitur AI membutuhkan konfigurasi provider aktif. Atur di Settings → AI Providers.');
        }

        return $this->applyConfig($config);
    }

    public function applyConfig(AiProviderConfig $config): AiAssistantSettings
    {
        $settings = new AiAssistantSettings(
            isEnabled: true,
            provider: $this->mapProvider($config),
            model: $config->default_model,
            systemPersona: config('ai-platform.persona'),
        );

        $provider = $settings->provider;

        Config::set('ai.default', $provider);

        if ($config->api_key !== null) {
            Config::set("ai.providers.{$provider}.key", $config->api_key);
        }

        $baseUrl = $config->base_url ?? $this->defaultBaseUrl($config->provider);

        if (filled($baseUrl)) {
            Config::set(
                "ai.providers.{$provider}.url",
                $this->normalizeBaseUrl($provider, $baseUrl),
            );
        }

        return $settings;
    }

    /**
     * @return array{provider: string|null, model: string|null, timeout: int}
     */
    public function promptOptions(User $user): array
    {
        $settings = $this->apply($user);

        return [
            'provider' => $settings->provider,
            'model' => filled($settings->model) ? $settings->model : null,
            'timeout' => 120,
        ];
    }

    public function assertEnabled(User $user): AiAssistantSettings
    {
        $settings = $this->settingsForUser($user);

        if ($settings === null) {
            throw new RuntimeException('Fitur AI membutuhkan konfigurasi provider aktif. Atur di Settings → AI Providers.');
        }

        return $settings;
    }

    public function providerLabel(AiProviderConfig $config): string
    {
        return $config->provider->label();
    }

    protected function mapProvider(AiProviderConfig $config): string
    {
        return match ($config->provider) {
            AiProvider::Anthropic => 'anthropic',
            AiProvider::Gemini => 'gemini',
            AiProvider::Ollama => 'ollama',
            AiProvider::OpenRouter => 'openrouter',
            AiProvider::NineRouter => '9router',
            AiProvider::Custom => 'custom-gateway',
        };
    }

    protected function defaultBaseUrl(?AiProvider $provider): ?string
    {
        return match ($provider) {
            AiProvider::Anthropic => 'https://api.anthropic.com/v1',
            AiProvider::Gemini => 'https://generativelanguage.googleapis.com/v1beta',
            AiProvider::Ollama => 'http://127.0.0.1:11434',
            AiProvider::OpenRouter => null,
            AiProvider::NineRouter => 'http://127.0.0.1:20128/v1',
            AiProvider::Custom => null,
            default => null,
        };
    }

    protected function normalizeBaseUrl(string $provider, string $url): string
    {
        $normalized = rtrim($url, '/');

        if (in_array($provider, self::OPENAI_COMPATIBLE_GATEWAYS, true) && ! str_ends_with($normalized, '/v1')) {
            return $normalized.'/v1';
        }

        return $normalized;
    }
}
