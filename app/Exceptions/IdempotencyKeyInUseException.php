<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a second request arrives with an Idempotency-Key that's
 * still being processed by a concurrent, not-yet-finished request. The
 * client should retry shortly — the original request will finish and
 * subsequent replays with the same key will get the real result.
 */
class IdempotencyKeyInUseException extends Exception
{
    public static function make(string $key): self
    {
        return new self("A request with Idempotency-Key '{$key}' is already being processed. Please retry shortly.");
    }
}
