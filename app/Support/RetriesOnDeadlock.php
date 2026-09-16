<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * LendFlow's current services (disbursement, repayment) each lock exactly
 * one loan row per transaction, which is the primary deadlock mitigation
 * — two transactions that only ever want ONE resource can't deadlock
 * against each other, no matter what order they run in. That's a
 * stronger guarantee than retry logic and costs nothing, so it's the
 * first line of defense (see docs/deadlocks.md).
 *
 * This trait is the SECOND line of defense, for the case a future
 * operation genuinely needs to lock multiple rows in one transaction
 * (the hypothetical batch-settlement scenario docs/deadlocks.md uses for
 * the demonstration). Consistent lock ordering should still be applied
 * first; this retry wrapper is what you reach for when ordering alone
 * isn't sufficient — e.g. the resource set isn't known upfront, or a
 * deadlock still occasionally slips through under high contention.
 */
trait RetriesOnDeadlock
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    protected function retryOnDeadlock(callable $operation, int $maxAttempts = 3)
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $operation();
            } catch (QueryException $e) {
                $isDeadlock = $e->getCode() === '40P01' || str_contains($e->getMessage(), 'deadlock detected');

                if (! $isDeadlock || $attempt >= $maxAttempts) {
                    throw $e;
                }

                // Small jittered backoff so two retrying transactions
                // don't immediately re-collide in lockstep.
                usleep(random_int(10_000, 50_000) * $attempt);
            }
        }
    }
}
