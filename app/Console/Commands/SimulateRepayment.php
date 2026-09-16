<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Repayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RepaymentService;
use App\Support\LedgerAccounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Runs ONE repayment attempt against a loan and prints a single JSON line
 * describing the outcome. This exists so the concurrency test suite can
 * spawn two of these as real, independent OS processes (via Symfony
 * Process) and observe genuine concurrent access to the same database
 * row — not a simulation within a single PHP process, which couldn't
 * exercise real Postgres lock contention at all.
 *
 * `--unsafe` swaps in a deliberately naive repayment path (no row lock)
 * for the "before" half of the race-condition demonstration. It is NEVER
 * used by any route or controller — it exists only so the test suite can
 * prove what would happen without the locking RepaymentService actually
 * uses. See docs/race-conditions.md.
 */
class SimulateRepayment extends Command
{
    protected $signature = 'repayment:simulate
        {loan : Loan ID to repay against}
        {amount : Repayment amount}
        {--unsafe : Use a naive, unlocked repayment path instead of the real RepaymentService}
        {--hold-ms=0 : Milliseconds to hold the loan row lock open after acquiring it, before reading/writing the balance}';

    protected $description = 'Simulate a single concurrent repayment attempt (used by the concurrency test suite).';

    public function handle(RepaymentService $repayments): int
    {
        $loanId = (int) $this->argument('loan');
        $amount = (float) $this->argument('amount');
        $holdMs = (int) $this->option('hold-ms');

        $actor = User::query()->where('role', User::ROLE_ADMIN)->first()
            ?? User::factory()->admin()->create();

        if ($this->option('unsafe')) {
            $this->runUnsafe($loanId, $amount, $holdMs);

            return self::SUCCESS;
        }

        RepaymentService::$afterLockHook = $holdMs > 0
            ? function () use ($holdMs) {
                usleep($holdMs * 1000);
            }
            : null;

        try {
            $repayment = $repayments->create(
                loan: Loan::findOrFail($loanId),
                actor: $actor,
                amount: $amount,
                paymentMethod: 'mpesa',
            );

            $this->outputResult([
                'success' => true,
                'repayment_id' => $repayment->id,
                'outstanding_principal_after' => (float) Loan::find($loanId)->outstanding_principal,
            ]);
        } catch (\Throwable $e) {
            $this->outputResult([
                'success' => false,
                'error' => $e->getMessage(),
                'outstanding_principal_after' => (float) Loan::find($loanId)->outstanding_principal,
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * The "before" path: reads the balance, optionally holds (simulating
     * a slow request), then writes a new balance computed from what it
     * read — with NO row lock. This is the exact shape of the bug
     * described in the project brief: two concurrent requests can both
     * read the same stale balance and both believe their payment is
     * valid.
     */
    private function runUnsafe(int $loanId, float $amount, int $holdMs): void
    {
        DB::transaction(function () use ($loanId, $amount, $holdMs) {
            // Deliberately NOT lockForUpdate() — this is the bug.
            $loan = Loan::where('id', $loanId)->first();
            $balanceRead = (float) $loan->outstanding_principal;

            if ($holdMs > 0) {
                usleep($holdMs * 1000);
            }

            // Naively accepts the payment as long as it looked valid
            // against the balance THIS process read — which may already
            // be stale by the time we get here.
            $newBalance = round($balanceRead - $amount, 2);

            $loan->outstanding_principal = $newBalance;
            $loan->save();

            $transaction = Transaction::create([
                'loan_id' => $loanId,
                'reference' => 'UNSAFE-'.now()->format('YmdHis').'-'.random_int(1000, 9999),
                'type' => Transaction::TYPE_REPAYMENT,
                'amount' => $amount,
                'status' => Transaction::STATUS_COMPLETED,
            ]);
            $transaction->ledgerEntries()->create([
                'account_code' => LedgerAccounts::CASH, 'entry_type' => 'debit', 'amount' => $amount,
            ]);
            $transaction->ledgerEntries()->create([
                'account_code' => LedgerAccounts::LOAN_PRINCIPAL_RECEIVABLE, 'entry_type' => 'credit', 'amount' => $amount,
            ]);

            $repayment = Repayment::create([
                'loan_id' => $loanId,
                'customer_id' => $loan->customer_id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'payment_method' => 'mpesa',
                'status' => Repayment::STATUS_COMPLETED,
                'paid_at' => now(),
            ]);

            $this->outputResult([
                'success' => true,
                'repayment_id' => $repayment->id,
                'balance_read' => $balanceRead,
                'outstanding_principal_after' => $newBalance,
            ]);
        });
    }

    private function outputResult(array $data): void
    {
        $this->line(json_encode($data));
    }
}
