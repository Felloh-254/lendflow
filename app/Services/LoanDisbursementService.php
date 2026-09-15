<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Support\LedgerAccounts;
use Illuminate\Support\Facades\DB;

/**
 * Disbursement is the first place in LendFlow where "the loan status
 * change" and "the financial record of it" absolutely must succeed or
 * fail together — see the project brief's invariant: there must never be
 * a state where Loan.status = active but the disbursement transaction
 * wasn't recorded. `DB::transaction()` here is what guarantees that: if
 * `LedgerService::post()` fails for any reason, the loan status update
 * inside the same transaction is rolled back with it.
 */
class LoanDisbursementService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogService $auditLog,
    ) {}

    public function disburse(Loan $loan, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $actor) {
            // Row-level lock for the duration of this transaction. If a
            // second disbursement request for the same loan somehow
            // arrives concurrently (e.g. a retried request without an
            // Idempotency-Key, or a bug upstream), it blocks here until
            // this transaction commits or rolls back, then re-reads the
            // now-`active` status and correctly refuses to disburse
            // twice — see docs/concurrency.md for the same pattern
            // applied to repayments, where it matters even more.
            $locked = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canTransitionTo(Loan::STATUS_ACTIVE)) {
                throw InvalidStateTransitionException::make('Loan', $locked->status, Loan::STATUS_ACTIVE);
            }

            $before = $locked->only(['status', 'disbursed_at']);

            $this->ledger->post(
                loan: $locked,
                type: Transaction::TYPE_DISBURSEMENT,
                amount: (float) $locked->principal_amount,
                entries: [
                    // Debit: the institution now has an asset (money owed
                    // back by the borrower) it didn't have before.
                    ['account' => LedgerAccounts::LOAN_PRINCIPAL_RECEIVABLE, 'type' => 'debit', 'amount' => (float) $locked->principal_amount],
                    // Credit: cash leaves the institution's hands.
                    ['account' => LedgerAccounts::CASH, 'type' => 'credit', 'amount' => (float) $locked->principal_amount],
                ],
            );

            $locked->status = Loan::STATUS_ACTIVE;
            $locked->disbursed_at = now();
            $locked->save();

            $this->auditLog->record(
                $actor,
                'loan.disbursed',
                $locked,
                $before,
                $locked->only(['status', 'disbursed_at']),
            );

            return $locked->fresh();
        });
    }
}
