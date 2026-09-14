<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown whenever a service attempts to move an entity (LoanApplication,
 * Loan, RepaymentSchedule) into a status it cannot legally reach from its
 * current status — e.g. approving an already-approved loan.
 */
class InvalidStateTransitionException extends Exception
{
    public static function make(string $entity, string $from, string $to): self
    {
        return new self("Cannot transition {$entity} from '{$from}' to '{$to}'.");
    }
}
