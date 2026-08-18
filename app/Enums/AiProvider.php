<?php

namespace App\Enums;

enum AiProvider: string
{
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case Ollama = 'ollama';
    case OpenRouter = 'openrouter';
    case NineRouter = '9router';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Anthropic',
            self::Gemini => 'Google Gemini',
            self::Ollama => 'Ollama (Local)',
            self::OpenRouter => 'OpenRouter',
            self::NineRouter => '9Router (Local)',
            self::Custom => 'Custom Gateway',
        };
    }
}
