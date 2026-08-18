<?php

namespace App\AI\Exceptions;

use RuntimeException;

class EntityNotFoundException extends RuntimeException
{
    /**
     * @param  list<string>  $available
     */
    public static function forKey(string $key, array $available = []): self
    {
        $message = "Unknown entity [{$key}].";

        if ($available !== []) {
            $message .= ' Available entity keys: '.implode(', ', $available).'. Use one of these exact keys.';
        }

        return new self($message);
    }
}
