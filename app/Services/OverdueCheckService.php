<?php

namespace App\Services;

use App\Events\LoanMarkedOverdue;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Models\Transaction;
use App\Support\LedgerAccounts;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic, documented penalty rule — same philosophy as
 * CreditAssessmentService: no magic numbers, easy to verify by hand.
 *
 * Penalty = max(FLAT_FEE, PERCENTAGE_RATE * remaining installment total)
 * — a flat minimum fee so a tiny remaining balance still carries a
 * meaningful deterrent, with a percentage component so a large overdue
 * installment carries a proportionally larger penalty.
 *
 * Idempotency guard: an installment is only ever penalized ONCE,
 * regardless of how many times this job runs or how much time passes.
 * The guard is `penalty_due == 0` — not `status == overdue` — because a
 * partial repayment against an already-overdue installment resets its
 * status to `partially_paid` (see RepaymentService), and re-checking
 * status alone would cause a second run to penalize it again. Once
 * penalty_due is set, it stays set; this job never touches it again for
 * that installment. See docs/scheduler.md.
 */
class OverdueCheckService
{
    private const FLAT_FEE = 100.0;

    private const PERCENTAGE_RATE = 0.02; // 2% of the remaining installment total

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @return array{installments_processed: int, loans_marked_overdue: int}
     */
    public function run(): array
    {
        $overdueSchedules = $this->findNewlyOverdueSchedules();

        $installmentsProcessed = 0;
        $loansMarkedOverdue = 0;

        foreach ($overdueSchedules as $schedule) {
            // Each installment gets its own transaction — a problem with
            // one loan (a data issue, a constraint violation) should
            // never abort processing for every other loan in the batch.
            // This is also what makes the job safe to simply re-run if
            // it's interrupted partway through: already-processed
            // installments are skipped by the penalty_due guard, and
            // whatever wasn't reached yet gets picked up on the next run.
            try {
                $wasNewlyMarkedOverdue = DB::transaction(fn () => $this->processInstallment($schedule));

                $installmentsProcessed++;

                if ($wasNewlyMarkedOverdue) {
                    $loansMarkedOverdue++;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [
            'installments_processed' => $installmentsProcessed,
            'loans_marked_overdue' => $loansMarkedOverdue,
        ];
    }

    /**
     * @return Collection<int, RepaymentSchedule>
     */
    private function findNewlyOverdueSchedules(): Collection
    {
        return RepaymentSchedule::query()
            ->where('due_date', '<', now()->toDateString())
            ->where('status', '!=', RepaymentSchedule::STATUS_PAID)
            ->where('penalty_due', 0) // the idempotency guard — see class docblock
            ->with('loan')
            ->get();
    }

    private function processInstallment(RepaymentSchedule $schedule): bool
    {
        // Re-fetch and lock — a repayment could be landing on this exact
        // installment concurrently with this scheduled job running.
        $locked = RepaymentSchedule::where('id', $schedule->id)->lockForUpdate()->firstOrFail();

        if ((float) $locked->penalty_due > 0) {
            // Lost the race to a concurrent run (or already processed by
            // the time this transaction acquired the lock) — nothing to
            // do.
            return false;
        }

        $remainingBeforePenalty = $locked->remainingTotal();
        $penalty = round(max(self::FLAT_FEE, self::PERCENTAGE_RATE * $remainingBeforePenalty), 2);

        $locked->penalty_due = $penalty;
        $locked->total_due = round((float) $locked->total_due + $penalty, 2);
        $locked->status = RepaymentSchedule::STATUS_OVERDUE;
        $locked->save();

        $loan = Loan::where('id', $locked->loan_id)->lockForUpdate()->firstOrFail();
        $loan->outstanding_fees = round((float) $loan->outstanding_fees + $penalty, 2);

        $wasNewlyMarkedOverdue = false;

        if ($loan->canTransitionTo(Loan::STATUS_OVERDUE)) {
            $loan->status = Loan::STATUS_OVERDUE;
            $wasNewlyMarkedOverdue = true;
        }

        $loan->save();

        // The penalty itself is booked to the ledger immediately, as
        // income the institution is now owed — distinct from a
        // repayment (which reduces a receivable); this INCREASES one.
        $this->ledger->post(
            loan: $loan,
            type: Transaction::TYPE_FEE,
            amount: $penalty,
            entries: [
                ['account' => LedgerAccounts::PENALTY_RECEIVABLE, 'type' => 'debit', 'amount' => $penalty],
                ['account' => LedgerAccounts::PENALTY_INCOME, 'type' => 'credit', 'amount' => $penalty],
            ],
        );

        $this->auditLog->record(
            null, // system-initiated, no HTTP actor
            'repayment_schedule.marked_overdue',
            $locked,
            null,
            ['penalty_due' => $penalty, 'installment_number' => $locked->installment_number],
        );

        if ($wasNewlyMarkedOverdue) {
            event(new LoanMarkedOverdue($loan));
        }

        return $wasNewlyMarkedOverdue;
    }
}
