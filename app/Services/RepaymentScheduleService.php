<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Illuminate\Support\Collection;

/**
 * Splits a loan's principal and interest evenly across its term into
 * equal monthly installments — the simplest schedule shape that's still
 * realistic, and one where the math is easy to verify by hand (useful for
 * an interview walkthrough). A production system would likely offer
 * reducing-balance amortization as well; that's a natural extension of
 * this service (a different `generate()` strategy), not a redesign of
 * the schedule table itself.
 *
 * Called from LoanDisbursementService, inside the same DB transaction as
 * the disbursement — a disbursed loan with no repayment schedule would be
 * exactly the kind of "partially happened" state this project's
 * transactional design is meant to rule out.
 */
class RepaymentScheduleService
{
    /**
     * @return Collection<int, RepaymentSchedule>
     */
    public function generate(Loan $loan): Collection
    {
        $termMonths = $loan->loanApplication->term_months;
        $principal = (float) $loan->principal_amount;
        $interest = (float) $loan->interest_amount;

        $baseMonthlyPrincipal = round($principal / $termMonths, 2);
        $baseMonthlyInterest = round($interest / $termMonths, 2);

        $startDate = ($loan->disbursed_at ?? now())->copy();

        $schedules = collect();
        $principalAllocated = 0.0;
        $interestAllocated = 0.0;

        for ($installment = 1; $installment <= $termMonths; $installment++) {
            $isLastInstallment = $installment === $termMonths;

            // The final installment absorbs whatever rounding remainder
            // is left over, so the sum of all installments always equals
            // the loan's principal/interest exactly — rounding never
            // silently loses or invents a few cents.
            $principalDue = $isLastInstallment
                ? round($principal - $principalAllocated, 2)
                : $baseMonthlyPrincipal;

            $interestDue = $isLastInstallment
                ? round($interest - $interestAllocated, 2)
                : $baseMonthlyInterest;

            $principalAllocated += $principalDue;
            $interestAllocated += $interestDue;

            $schedules->push($loan->repaymentSchedules()->create([
                'installment_number' => $installment,
                'due_date' => $startDate->copy()->addMonthsNoOverflow($installment)->toDateString(),
                'principal_due' => $principalDue,
                'interest_due' => $interestDue,
                'total_due' => round($principalDue + $interestDue, 2),
                'status' => RepaymentSchedule::STATUS_PENDING,
            ]));
        }

        return $schedules;
    }
}
