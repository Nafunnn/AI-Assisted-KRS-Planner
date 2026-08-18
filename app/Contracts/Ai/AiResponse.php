<?php

namespace App\Contracts\Ai;

class AiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $content,
        public array $meta = [],
    ) {}
}
