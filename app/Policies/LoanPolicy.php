<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // scoped by query in the controller, same pattern as LoanApplicationPolicy
    }

    public function view(User $user, Loan $loan): bool
    {
        if ($user->isRole(User::ROLE_LOAN_OFFICER, User::ROLE_MANAGER, User::ROLE_ADMIN)) {
            return true;
        }

        return $loan->customer->user_id === $user->id;
    }

    /**
     * Disbursement moves real money — restricted to Manager/Admin, same
     * as the approve/reject decision on the originating application. A
     * Loan Officer, who may have performed the credit assessment, still
     * cannot disburse: assessment and disbursement authority are kept
     * separate on purpose (segregation of duties).
     */
    public function disburse(User $user, Loan $loan): bool
    {
        return $user->isRole(User::ROLE_MANAGER, User::ROLE_ADMIN);
    }

    /**
     * Only the loan's own customer can make a repayment against it — this
     * matches the spec's "Customer: make repayments" capability. Staff
     * roles never post a repayment through this endpoint; a real
     * institution's staff-assisted payment flow (e.g. recording a walk-in
     * cash payment) would be a deliberately separate, more heavily
     * audited capability, not a side door through the customer endpoint.
     */
    public function repay(User $user, Loan $loan): bool
    {
        return $loan->customer->user_id === $user->id;
    }
}
