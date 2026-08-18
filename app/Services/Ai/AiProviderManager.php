<?php

namespace App\Services\Ai;

use App\Contracts\Ai\AiProviderDriver;
use App\Enums\AiProvider;
use App\Models\AiProviderConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiProviderManager
{
    public function driver(?AiProviderConfig $config = null): AiProviderDriver
    {
        if ($config === null) {
            throw new RuntimeException('Konfigurasi AI provider belum dipilih.');
        }

        return match ($config->provider) {
            AiProvider::Anthropic => new OpenAiCompatibleDriver($this->client($config), $config->default_model ?? 'claude-3-5-sonnet-latest'),
            AiProvider::Gemini => new OpenAiCompatibleDriver($this->client($config), $config->default_model ?? 'gemini-2.0-flash'),
            AiProvider::Ollama => new OpenAiCompatibleDriver($this->client($config), $config->default_model ?? 'llama3'),
            AiProvider::NineRouter => new OpenAiCompatibleDriver($this->client($config), $config->default_model ?? 'claude-sonnet-4'),
            AiProvider::OpenRouter => new OpenAiCompatibleDriver($this->client($config), $config->default_model ?? 'anthropic/claude-sonnet-4'),
            AiProvider::Custom => new OpenAiCompatibleDriver($this->client($config), $config->default_model ?? 'default'),
        };
    }

    private function client(AiProviderConfig $config): PendingRequest
    {
        $baseUrl = $config->base_url ?? match ($config->provider) {
            AiProvider::Anthropic => 'https://api.anthropic.com/v1',
            AiProvider::Gemini => 'https://generativelanguage.googleapis.com/v1beta/openai',
            AiProvider::Ollama => 'http://127.0.0.1:11434/v1',
            AiProvider::NineRouter => 'http://127.0.0.1:20128/v1',
            AiProvider::OpenRouter => 'https://openrouter.ai/api/v1',
            AiProvider::Custom => throw new RuntimeException('Base URL wajib diisi untuk provider custom.'),
        };

        $request = Http::baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->timeout(60);

        if ($config->api_key) {
            $request = $request->withToken($config->api_key);
        }

        return $request;
    }
}
