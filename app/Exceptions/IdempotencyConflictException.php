<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when an Idempotency-Key is reused with a request body that
 * differs from the original request — the key must uniquely identify one
 * logical operation, so a mismatched replay is rejected rather than
 * silently returning the original (unrelated) result.
 */
class IdempotencyConflictException extends Exception
{
    public static function make(string $key): self
    {
        return new self("Idempotency-Key '{$key}' was already used with a different request body.");
    }
}
