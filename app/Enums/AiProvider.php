<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case Ollama = 'ollama';
    case OpenRouter = 'openrouter';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Anthropic => 'Anthropic',
            self::Gemini => 'Google Gemini',
            self::Ollama => 'Ollama (Local)',
            self::OpenRouter => 'OpenRouter',
            self::Custom => 'Custom',
        };
    }
}
