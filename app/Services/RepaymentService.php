<?php

namespace App\Services;

use App\Events\LoanCompleted;
use App\Events\RepaymentReceived;
use App\Exceptions\InsufficientOutstandingBalanceException;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use App\Models\Transaction;
use App\Models\User;
use App\Support\LedgerAccounts;
use Illuminate\Support\Facades\DB;

/**
 * This is THE function docs/concurrency.md and docs/race-conditions.md
 * are about. Two repayment requests against the same loan, arriving
 * concurrently, must not both be allowed to consume the same outstanding
 * balance — see Phase 8 for the dedicated test suite proving it.
 *
 * The concurrency-safety property: `Loan::where(...)->lockForUpdate()`
 * takes a row-level lock inside this method's DB transaction. If two
 * requests for the same loan arrive at (near) the same instant, the
 * SECOND transaction's lockForUpdate() call blocks until the FIRST
 * transaction commits or rolls back — it cannot read outstanding_principal
 * until the first repayment has already been fully applied and
 * committed. This turns a "read stale balance, then both overpay" race
 * into a strictly serialized pair of operations, even under real
 * concurrent load.
 */
class RepaymentService
{
    /**
     * Testing-only instrumentation: an optional callable invoked
     * immediately after the loan row lock is acquired, before any reads
     * of the (now-locked) balance or writes happen. Used exclusively by
     * the concurrency test suite (see SimulateRepayment, which sets this
     * from a `--hold-ms` CLI flag) to deterministically hold the
     * transaction open for a controlled duration — so two real,
     * concurrent OS processes can be proven to serialize on the lock
     * rather than the test relying on process-scheduling luck to even
     * observe contention.
     *
     * Always null in production. No controller, route, or service sets
     * it — grep the codebase and the only assignment is in the artisan
     * command used by the concurrency tests.
     */
    public static $afterLockHook = null;

    public function __construct(
        private readonly RepaymentAllocationService $allocation,
        private readonly LedgerService $ledger,
        private readonly AuditLogService $auditLog,
    ) {}

    public function create(
        Loan $loan,
        User $actor,
        float $amount,
        string $paymentMethod,
        ?string $externalReference = null,
    ): Repayment {
        return DB::transaction(function () use ($loan, $actor, $amount, $paymentMethod, $externalReference) {
            // See the class docblock — this lock is the entire mechanism
            // that makes concurrent repayments safe. Every other query in
            // this method reads through this locked row (or rows derived
            // from it), so nothing here can observe a stale balance.
            $locked = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            if (self::$afterLockHook) {
                (self::$afterLockHook)();
            }

            $outstanding = $locked->totalOutstanding();

            if ($amount > $outstanding + 0.005) {
                throw InsufficientOutstandingBalanceException::make($amount, $outstanding);
            }

            $plan = $this->allocation->allocate($locked, $amount);

            $totalPenaltyApplied = 0.0;
            $totalPrincipalApplied = 0.0;
            $totalInterestApplied = 0.0;

            foreach ($plan as $item) {
                /** @var RepaymentSchedule $schedule */
                $schedule = $item['schedule'];

                $schedule->penalty_paid = round((float) $schedule->penalty_paid + $item['penalty_applied'], 2);
                $schedule->principal_paid = round((float) $schedule->principal_paid + $item['principal_applied'], 2);
                $schedule->interest_paid = round((float) $schedule->interest_paid + $item['interest_applied'], 2);
                $schedule->status = $schedule->isFullyPaid()
                    ? RepaymentSchedule::STATUS_PAID
                    : RepaymentSchedule::STATUS_PARTIALLY_PAID;
                $schedule->save();

                $totalPenaltyApplied += $item['penalty_applied'];
                $totalPrincipalApplied += $item['principal_applied'];
                $totalInterestApplied += $item['interest_applied'];
            }

            $totalPenaltyApplied = round($totalPenaltyApplied, 2);
            $totalPrincipalApplied = round($totalPrincipalApplied, 2);
            $totalInterestApplied = round($totalInterestApplied, 2);

            $locked->outstanding_fees = round((float) $locked->outstanding_fees - $totalPenaltyApplied, 2);
            $locked->outstanding_principal = round((float) $locked->outstanding_principal - $totalPrincipalApplied, 2);
            $locked->outstanding_interest = round((float) $locked->outstanding_interest - $totalInterestApplied, 2);

            $isFullyRepaid = $locked->outstanding_principal <= 0.005
                && $locked->outstanding_interest <= 0.005
                && $locked->outstanding_fees <= 0.005;

            if ($isFullyRepaid) {
                $locked->outstanding_principal = 0;
                $locked->outstanding_interest = 0;
                $locked->outstanding_fees = 0;

                if ($locked->canTransitionTo(Loan::STATUS_COMPLETED)) {
                    $locked->status = Loan::STATUS_COMPLETED;
                    $locked->completed_at = now();
                }
            }

            $locked->save();

            $entries = [
                // Debit: cash comes IN to the institution.
                ['account' => LedgerAccounts::CASH, 'type' => 'debit', 'amount' => round($amount, 2)],
            ];

            if ($totalPrincipalApplied > 0) {
                $entries[] = ['account' => LedgerAccounts::LOAN_PRINCIPAL_RECEIVABLE, 'type' => 'credit', 'amount' => $totalPrincipalApplied];
            }

            if ($totalInterestApplied > 0) {
                $entries[] = ['account' => LedgerAccounts::INTEREST_RECEIVABLE, 'type' => 'credit', 'amount' => $totalInterestApplied];
            }

            if ($totalPenaltyApplied > 0) {
                $entries[] = ['account' => LedgerAccounts::PENALTY_RECEIVABLE, 'type' => 'credit', 'amount' => $totalPenaltyApplied];
            }

            $transaction = $this->ledger->post(
                loan: $locked,
                type: Transaction::TYPE_REPAYMENT,
                amount: round($amount, 2),
                entries: $entries,
                externalReference: $externalReference,
            );

            $repayment = Repayment::create([
                'loan_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'transaction_id' => $transaction->id,
                'amount' => round($amount, 2),
                'payment_method' => $paymentMethod,
                'external_reference' => $externalReference,
                'status' => Repayment::STATUS_COMPLETED,
                'paid_at' => now(),
            ]);

            $this->auditLog->record(
                $actor,
                'repayment.received',
                $locked,
                null,
                [
                    'repayment_id' => $repayment->id,
                    'amount' => round($amount, 2),
                    'penalty_applied' => $totalPenaltyApplied,
                    'principal_applied' => $totalPrincipalApplied,
                    'interest_applied' => $totalInterestApplied,
                    'outstanding_principal' => (float) $locked->outstanding_principal,
                    'outstanding_interest' => (float) $locked->outstanding_interest,
                    'outstanding_fees' => (float) $locked->outstanding_fees,
                    'loan_status' => $locked->status,
                ],
            );

            event(new RepaymentReceived($repayment));

            if ($isFullyRepaid && $locked->status === Loan::STATUS_COMPLETED) {
                event(new LoanCompleted($locked));
            }

            return $repayment;
        });
    }
}
