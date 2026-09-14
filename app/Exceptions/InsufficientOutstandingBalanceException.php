<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a repayment amount would reduce a loan's outstanding balance
 * below zero — including the case where a concurrent repayment already
 * consumed the balance this request believed was still available.
 */
class InsufficientOutstandingBalanceException extends Exception
{
    public static function make(float $attempted, float $available): self
    {
        return new self(
            sprintf('Repayment of %.2f exceeds outstanding balance of %.2f.', $attempted, $available)
        );
    }
}
