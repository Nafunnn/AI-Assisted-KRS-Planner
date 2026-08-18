<?php

namespace App\Services\Ai;

use App\Contracts\Ai\AiProviderDriver;
use App\Contracts\Ai\AiResponse;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

class OpenAiCompatibleDriver implements AiProviderDriver
{
    public function __construct(
        private PendingRequest $client,
        private string $model,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): AiResponse
    {
        $response = $this->client->post('/chat/completions', [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.4,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?? 'Permintaan AI gagal.');
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new RuntimeException('Respons AI tidak valid.');
        }

        return new AiResponse($content, [
            'model' => $response->json('model'),
        ]);
    }
}
