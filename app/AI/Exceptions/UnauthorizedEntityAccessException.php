<?php

namespace App\AI\Exceptions;

use RuntimeException;

class UnauthorizedEntityAccessException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("You are not authorized to access entity [{$key}].");
    }
}
