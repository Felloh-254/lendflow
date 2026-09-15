<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\LoanApplicationPolicy;
use App\Policies\LoanPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Model => Policy map.
     *
     * Laravel 11 will auto-discover these by naming convention alone
     * (App\Models\Customer -> App\Policies\CustomerPolicy), but we register
     * them explicitly here — it's one place to look to see every
     * resource-level authorization rule in the system, which matters more
     * for a project meant to be read than the few lines it saves.
     */
    protected array $policies = [
        User::class => UserPolicy::class,
        Customer::class => CustomerPolicy::class,
        LoanApplication::class => LoanApplicationPolicy::class,
        Loan::class => LoanPolicy::class,
    ];

    public function register(): void {}

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Coarse, system-wide actions that don't map to a single Eloquent
        // model instance — these stay as simple Gates rather than
        // Policies, since there's no per-record decision to make.
        Gate::define('manage-loan-products', fn (User $user) => $user->isRole(User::ROLE_ADMIN));
        Gate::define('view-audit-logs', fn (User $user) => $user->isRole(User::ROLE_ADMIN));
        Gate::define('manage-system-configuration', fn (User $user) => $user->isRole(User::ROLE_ADMIN));

        // Loan-portfolio visibility: managers and admins can see the full
        // book; loan officers see only what's assigned to them (enforced
        // via query scoping in the controller/service once loans exist,
        // not here — a Gate can say "may view the portfolio index" but
        // can't scope *which* rows, that's a query concern).
        Gate::define('view-loan-portfolio', fn (User $user) => $user->isRole(
            User::ROLE_LOAN_OFFICER, User::ROLE_MANAGER, User::ROLE_ADMIN
        ));
    }
}
