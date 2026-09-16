<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Collection;

/**
 * Allocation strategy: oldest unpaid installment first (FIFO by
 * installment_number), and within an installment: penalty, then
 * interest, then principal. Penalty-first is deliberate — it's the
 * conventional priority in consumer lending (a customer catching up on
 * an overdue installment clears the late fee before the payment counts
 * toward reducing what they still owe on interest/principal), and it
 * gives a customer a clear incentive signal: pay promptly and there's
 * never a penalty to clear first.
 *
 * This is a pure allocation calculator — it returns a plan (which
 * schedules get how much penalty/principal/interest applied), it doesn't
 * persist anything itself. RepaymentService is responsible for applying
 * the plan and saving it, inside its own locked transaction.
 */
class RepaymentAllocationService
{
    /**
     * @return Collection<int, array{schedule: RepaymentSchedule, penalty_applied: float, principal_applied: float, interest_applied: float}>
     */
    public function allocate(Loan $loan, float $amount): Collection
    {
        $remaining = round($amount, 2);
        $plan = collect();

        $pendingSchedules = $loan->repaymentSchedules()
            ->whereIn('status', [RepaymentSchedule::STATUS_PENDING, RepaymentSchedule::STATUS_PARTIALLY_PAID, RepaymentSchedule::STATUS_OVERDUE])
            ->orderBy('installment_number')
            ->get();

        foreach ($pendingSchedules as $schedule) {
            if ($remaining <= 0.005) {
                break;
            }

            $penaltyOwed = $schedule->remainingPenalty();
            $penaltyApplied = min($penaltyOwed, $remaining);
            $remaining = round($remaining - $penaltyApplied, 2);

            $interestOwed = $schedule->remainingInterest();
            $interestApplied = min($interestOwed, $remaining);
            $remaining = round($remaining - $interestApplied, 2);

            $principalOwed = $schedule->remainingPrincipal();
            $principalApplied = min($principalOwed, $remaining);
            $remaining = round($remaining - $principalApplied, 2);

            if ($penaltyApplied > 0 || $interestApplied > 0 || $principalApplied > 0) {
                $plan->push([
                    'schedule' => $schedule,
                    'penalty_applied' => $penaltyApplied,
                    'principal_applied' => $principalApplied,
                    'interest_applied' => $interestApplied,
                ]);
            }
        }

        return $plan;
    }
}
