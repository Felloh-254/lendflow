<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Posts a balanced double-entry transaction: one Transaction row plus two
 * or more LedgerEntry rows whose debits equal their credits. This is the
 * ONLY place in the app that writes to `transactions` or `ledger_entries`
 * — every financial operation (disbursement now, repayments in Phase 8)
 * goes through `post()`, so "is the ledger balanced" only has to be
 * verified in one function.
 *
 * We deliberately do NOT rely on `loans.outstanding_balance` alone to
 * represent financial history (see the project brief) — this ledger is
 * the auditable source of truth; outstanding balances on the Loan row are
 * a denormalized convenience for fast reads, always derived from and kept
 * in sync with ledger activity, never the other way around.
 */
class LedgerService
{
    /**
     * @param  array<int, array{account: string, type: string, amount: float}>  $entries
     */
    public function post(Loan $loan, string $type, float $amount, array $entries, ?string $externalReference = null): Transaction
    {
        $this->assertBalanced($entries);

        return DB::transaction(function () use ($loan, $type, $amount, $entries, $externalReference) {
            $transaction = Transaction::create([
                'loan_id' => $loan->id,
                'reference' => $this->generateReference($type),
                'type' => $type,
                'amount' => $amount,
                'status' => Transaction::STATUS_COMPLETED,
                'external_reference' => $externalReference,
            ]);

            foreach ($entries as $entry) {
                $transaction->ledgerEntries()->create([
                    'account_code' => $entry['account'],
                    'entry_type' => $entry['type'],
                    'amount' => $entry['amount'],
                ]);
            }

            return $transaction;
        });
    }

    /**
     * @param  array<int, array{account: string, type: string, amount: float}>  $entries
     */
    private function assertBalanced(array $entries): void
    {
        $debits = 0.0;
        $credits = 0.0;

        foreach ($entries as $entry) {
            if ($entry['type'] === 'debit') {
                $debits += $entry['amount'];
            } else {
                $credits += $entry['amount'];
            }
        }

        // Cents-level float comparison tolerance — amounts are persisted
        // as fixed-point decimal(14,2) in Postgres; this check just needs
        // to catch a programmer error (mismatched entries), not chase
        // floating point noise smaller than a cent.
        if (abs($debits - $credits) > 0.005) {
            throw new \RuntimeException(
                sprintf('Ledger entries are not balanced: debits=%.2f, credits=%.2f.', $debits, $credits)
            );
        }
    }

    private function generateReference(string $type): string
    {
        return strtoupper($type).'-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
    }
}
