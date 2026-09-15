<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\User;

class LoanApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        // Every role can hit the index; the controller scopes *which*
        // rows come back (own applications for a customer, assigned +
        // unassigned for a loan officer, everything for manager/admin).
        return true;
    }

    public function view(User $user, LoanApplication $application): bool
    {
        if ($user->isRole(User::ROLE_MANAGER, User::ROLE_ADMIN)) {
            return true;
        }

        if ($user->isRole(User::ROLE_LOAN_OFFICER)) {
            return is_null($application->assigned_loan_officer_id)
                || $application->assigned_loan_officer_id === $user->id;
        }

        return $application->customer->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isRole(User::ROLE_CUSTOMER);
    }

    public function submit(User $user, LoanApplication $application): bool
    {
        return $application->customer->user_id === $user->id;
    }

    public function cancel(User $user, LoanApplication $application): bool
    {
        return $application->customer->user_id === $user->id;
    }

    /**
     * A loan officer may assess an application that is either unassigned
     * or already assigned to them — never one claimed by a colleague.
     * This is the row-level rule that distinguishes "may perform this
     * kind of action" (a Gate could answer that) from "may perform it on
     * THIS record" (only a Policy, with the instance in hand, can).
     */
    public function assess(User $user, LoanApplication $application): bool
    {
        if (! $user->isRole(User::ROLE_LOAN_OFFICER)) {
            return false;
        }

        return is_null($application->assigned_loan_officer_id)
            || $application->assigned_loan_officer_id === $user->id;
    }

    public function decide(User $user, LoanApplication $application): bool
    {
        return $user->isRole(User::ROLE_MANAGER, User::ROLE_ADMIN);
    }
}
