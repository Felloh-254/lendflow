<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management (listing staff/customers, changing role or status) is
 * an admin-only capability. This is intentionally a single, obvious
 * gatekeeper rather than a scattering of `if ($user->role === 'admin')`
 * checks across controllers — every admin-only action funnels through
 * here, so the rule only has to be correct in one place.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isRole(User::ROLE_ADMIN);
    }

    public function view(User $user, User $target): bool
    {
        return $user->isRole(User::ROLE_ADMIN) || $user->id === $target->id;
    }

    public function update(User $user, User $target): bool
    {
        return $user->isRole(User::ROLE_ADMIN);
    }

    /**
     * Even an admin cannot demote or suspend themselves through this
     * endpoint — prevents an admin from accidentally locking themselves
     * out, and prevents a compromised session from disabling audit trails
     * by suspending the only other admins. A break-glass procedure for
     * this lives outside the API (direct DB access), not as a self-service
     * action.
     */
    public function modifyOwnAccount(User $user, User $target): bool
    {
        return $user->id !== $target->id;
    }
}
