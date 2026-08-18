<?php

namespace App\AI\Services;

use App\Enums\AiProvider;
use App\Models\AiProviderConfig;
use App\Models\User;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class AiProviderTestService
{
    public function __construct(private AiConfigResolver $configResolver) {}

    /**
     * @return array{status: string, message: string, reply?: string, provider?: string, provider_label?: string, model?: string|null, latency_ms?: int}
     */
    public function test(AiProviderConfig $config): array
    {
        if ($config->provider === AiProvider::Custom && blank($config->base_url)) {
            return [
                'status' => 'error',
                'message' => 'Base URL wajib diisi untuk provider Custom Gateway.',
            ];
        }

        $settings = $this->configResolver->applyConfig($config);
        $started = microtime(true);

        try {
            $agent = new AnonymousAgent(
                instructions: 'You are a connectivity test assistant. Reply briefly.',
                messages: [],
                tools: [],
            );

            $response = $agent->prompt(
                'Balas hanya dengan kata: OK',
                provider: $settings->provider,
                model: filled($settings->model) ? $settings->model : null,
                timeout: 30,
            );

            $reply = trim((string) ($response->text ?? ''));

            if ($reply === '') {
                return [
                    'status' => 'error',
                    'message' => 'Provider merespons tanpa teks. Periksa model dan endpoint.',
                    'provider' => $settings->provider,
                    'provider_label' => $config->provider->label(),
                    'model' => $settings->model,
                    'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                ];
            }

            return [
                'status' => 'ok',
                'message' => 'Koneksi berhasil.',
                'reply' => mb_substr($reply, 0, 200),
                'provider' => $settings->provider,
                'provider_label' => $config->provider->label(),
                'model' => $settings->model,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        } catch (RateLimitedException) {
            return [
                'status' => 'error',
                'message' => "Provider {$config->provider->label()} sedang rate limit. Coba lagi nanti atau ganti model.",
                'provider' => $settings->provider,
                'provider_label' => $config->provider->label(),
                'model' => $settings->model,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => $this->friendlyError($config, $e),
                'provider' => $settings->provider,
                'provider_label' => $config->provider->label(),
                'model' => $settings->model,
            ];
        }
    }

    /**
     * Build an unsaved config model from request input for draft testing.
     */
    public function configFromInput(User $user, array $data, ?AiProviderConfig $existing = null): AiProviderConfig
    {
        $config = $existing ?? new AiProviderConfig(['user_id' => $user->id]);
        $config->provider = $data['provider'];
        $config->base_url = $data['base_url'] ?? null;
        $config->default_model = $data['default_model'] ?? null;

        if (filled($data['api_key'] ?? null)) {
            $config->api_key = $data['api_key'];
        } elseif ($existing !== null) {
            $config->api_key = $existing->api_key;
        } else {
            $config->api_key = null;
        }

        return $config;
    }

    protected function friendlyError(AiProviderConfig $config, Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'Connection refused') || str_contains($message, 'cURL error 7')) {
            return match ($config->provider) {
                AiProvider::NineRouter => 'Tidak dapat terhubung ke 9Router. Pastikan 9Router berjalan di http://127.0.0.1:20128.',
                AiProvider::Ollama => 'Tidak dapat terhubung ke Ollama. Pastikan Ollama berjalan di http://127.0.0.1:11434.',
                default => 'Tidak dapat terhubung ke endpoint provider. Periksa Base URL dan pastikan layanan berjalan.',
            };
        }

        if (str_contains($message, '401') || str_contains($message, 'Unauthorized')) {
            return 'API key ditolak. Periksa kredensial provider.';
        }

        if (str_contains($message, '404') || str_contains($message, 'model')) {
            return 'Model tidak ditemukan atau endpoint salah. Periksa Default Model dan Base URL.';
        }

        return 'Uji koneksi gagal: '.$message;
    }
}
