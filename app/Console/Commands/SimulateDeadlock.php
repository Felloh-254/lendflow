<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * LendFlow's real hot paths (disbursement, repayment) only ever lock ONE
 * loan row per transaction, so they cannot deadlock against each other —
 * that's a deliberate design property, documented in docs/deadlocks.md.
 * To produce an ACTUAL, reproducible deadlock (not a hypothetical one),
 * this command simulates the kind of multi-row operation that COULD
 * deadlock if written carelessly — e.g. a hypothetical batch settlement
 * job that needs to lock two loans in the same transaction.
 *
 * Run two of these concurrently against the same pair of loans with
 * `--reverse` on one of them, and PostgreSQL's own deadlock detector will
 * abort one of the two transactions with SQLSTATE 40P01.
 */
class SimulateDeadlock extends Command
{
    use \App\Support\RetriesOnDeadlock;

    protected $signature = 'deadlock:simulate
        {loanA : First loan ID}
        {loanB : Second loan ID}
        {--reverse : Lock loanB before loanA instead of loanA before loanB}
        {--consistent-order : Ignore the requested order and always lock in ascending ID order (mitigation 1: prevention)}
        {--retry : Wrap the operation in retryOnDeadlock() instead of failing immediately (mitigation 2: recovery)}
        {--hold-ms=1000 : Milliseconds to hold the first lock before attempting the second}';

    protected $description = 'Simulate two loans being locked in a given order (used by the deadlock test suite).';

    public function handle(): int
    {
        $loanA = (int) $this->argument('loanA');
        $loanB = (int) $this->argument('loanB');
        $holdMs = (int) $this->option('hold-ms');

        [$first, $second] = $this->lockOrder($loanA, $loanB);

        $attemptsMade = 0;

        try {
            $operation = function () use ($first, $second, $holdMs, &$attemptsMade) {
                $attemptsMade++;

                DB::transaction(function () use ($first, $second, $holdMs) {
                    Loan::where('id', $first)->lockForUpdate()->first();

                    usleep($holdMs * 1000);

                    Loan::where('id', $second)->lockForUpdate()->first();
                });
            };

            if ($this->option('retry')) {
                $this->retryOnDeadlock($operation);
            } else {
                $operation();
            }

            $this->line(json_encode(['success' => true, 'locked_order' => [$first, $second], 'attempts' => $attemptsMade]));
        } catch (QueryException $e) {
            // Postgres SQLSTATE 40P01 = deadlock_detected. This process
            // was chosen as the "victim" — Postgres always aborts exactly
            // one of the two deadlocked transactions so the other can
            // proceed; which one is chosen is not something application
            // code controls or should rely on.
            if ($e->getCode() === '40P01' || str_contains($e->getMessage(), 'deadlock detected')) {
                $this->line(json_encode(['success' => false, 'deadlock_detected' => true, 'locked_order' => [$first, $second]]));

                return self::SUCCESS;
            }

            $this->line(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function lockOrder(int $loanA, int $loanB): array
    {
        if ($this->option('consistent-order')) {
            return $loanA < $loanB ? [$loanA, $loanB] : [$loanB, $loanA];
        }

        return $this->option('reverse') ? [$loanB, $loanA] : [$loanA, $loanB];
    }
}
