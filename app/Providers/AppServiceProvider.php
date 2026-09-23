<?php

namespace App\Providers;

use App\Events\LoanApproved;
use App\Events\LoanCompleted;
use App\Events\RepaymentReceived;
use App\Listeners\AuditNotificationSubscriber;
use App\Listeners\GenerateStatementListener;
use App\Listeners\SendLoanApprovalNotificationListener;
use App\Listeners\SendRepaymentConfirmationListener;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\LoanApplicationPolicy;
use App\Policies\LoanPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

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

    /**
     * Event => Listener map. Registered explicitly, same reasoning as
     * the policy map above — one place to see every domain event and
     * what reacts to it. See docs/events.md.
     */
    protected array $listen = [
        LoanApproved::class => [
            SendLoanApprovalNotificationListener::class,
        ],
        RepaymentReceived::class => [
            SendRepaymentConfirmationListener::class,
        ],
        LoanCompleted::class => [
            GenerateStatementListener::class,
        ],
        // LoanDisbursed has no direct listener of its own — it's only
        // consumed by AuditNotificationSubscriber below.
    ];

    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }

        Event::subscribe(AuditNotificationSubscriber::class);

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
