<?php

namespace App\AI\DTO;

class AiAssistantSettings
{
    public function __construct(
        public readonly bool $isEnabled,
        public readonly string $provider,
        public readonly ?string $model,
        public readonly ?string $systemPersona = null,
        public readonly float $temperature = 0.4,
        public readonly int $maxTokens = 4096,
    ) {}
}
