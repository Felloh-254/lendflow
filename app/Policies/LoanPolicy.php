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
}
