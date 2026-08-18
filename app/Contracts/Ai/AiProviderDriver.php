<?php

namespace App\Contracts\Ai;

interface AiProviderDriver
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): AiResponse;
}
