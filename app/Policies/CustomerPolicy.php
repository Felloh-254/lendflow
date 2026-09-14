<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * Authorization rules for a customer's profile record.
 *
 * Kept deliberately narrow: a customer can only ever see/edit their own
 * profile. Staff roles get read access (needed for credit assessment and
 * approval review in later phases) but never write access — profile edits
 * always flow through the customer themselves.
 */
class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        if ($user->isRole(User::ROLE_LOAN_OFFICER, User::ROLE_MANAGER, User::ROLE_ADMIN)) {
            return true;
        }

        return $user->id === $customer->user_id;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->id === $customer->user_id;
    }
}
