<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;

/**
 * A Loan is created exactly once, at the moment its LoanApplication is
 * approved — called from inside LoanApplicationService::approve()'s
 * transaction, so "application approved" and "loan created" are
 * atomically the same event. There is deliberately no separate "create a
 * loan" endpoint; a Loan without an approved LoanApplication behind it
 * shouldn't be able to exist.
 */
class LoanService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function createFromApplication(LoanApplication $application, User $actor): Loan
    {
        $product = $application->loanProduct;
        $principal = (float) $application->amount_requested;

        // Simple (non-compounding) interest over the loan's term:
        // interest = principal * annual_rate * (term_months / 12).
        // A flat-rate model is the right level of realism for this
        // project's purpose — the goal is demonstrating correct
        // transactional/ledger mechanics, not building a production
        // amortization engine with day-count conventions.
        $interest = round($principal * ((float) $product->interest_rate / 100) * ($application->term_months / 12), 2);
        $total = round($principal + $interest, 2);

        $loan = Loan::create([
            'customer_id' => $application->customer_id,
            'loan_application_id' => $application->id,
            'loan_product_id' => $application->loan_product_id,
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'total_amount' => $total,
            'outstanding_principal' => $principal,
            'outstanding_interest' => $interest,
            'status' => Loan::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $this->auditLog->record($actor, 'loan.created', $loan, null, $loan->toArray());

        return $loan;
    }
}
