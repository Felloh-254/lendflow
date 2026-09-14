# Authorization

## What it is

Laravel offers two related authorization primitives:

- **Gates** — closures registered against a name (`Gate::define('view-audit-logs', ...)`), used for actions that don't correspond to a single Eloquent record (e.g. "can you see the audit log index at all?").
- **Policies** — classes with one method per action, scoped to a specific model (`CustomerPolicy::update(User $user, Customer $customer)`), used when the answer depends on *which record* is being acted on.

## Why we need it

LendFlow has four roles with meaningfully different permissions, and several of those permissions are row-level ("a customer can view *their own* profile" — not "customers can view profiles"). A boolean role check (`if ($user->role === 'admin')`) sprinkled through controllers can't express that distinction cleanly, and it scatters the authorization rule across every place it's needed — miss one spot and you have a silent privilege-escalation bug that's easy to overlook in review.

## How we're using it in LendFlow

- Every resource that needs row-level rules gets a **Policy** (`UserPolicy`, `CustomerPolicy`, and — from Phase 5 onward — `LoanApplicationPolicy`, `LoanPolicy`).
- Every resource-agnostic, system-wide permission gets a **Gate**, defined once in `AppServiceProvider::boot()` (`manage-loan-products`, `view-audit-logs`, `manage-system-configuration`, `view-loan-portfolio`).
- Controllers call `$this->authorize('action', $model)` and nothing else — no `if ($user->role === ...)` anywhere in a controller. If you `grep -r "role ===" app/Http/Controllers`, it should come back empty.
- **Policies and Gates decide "may this happen", not "which rows are visible."** A loan officer's `view-loan-portfolio` Gate answers "can you look at the portfolio index at all" — but *which* applications a given loan officer sees (assigned-to-them vs. everything) is a query-scoping concern, handled in the service/controller with an explicit `where('assigned_to', $user->id)` clause, not smuggled into the Gate. Mixing those two concerns makes both harder to test in isolation.

## A concrete example: admin self-modification

`UserPolicy::modifyOwnAccount()` blocks an admin from suspending or changing their *own* role through the admin user-management endpoint, even though `UserPolicy::update()` would otherwise allow it. This is deliberately a **separate policy method**, checked as a second `$this->authorize()` call, rather than folded into `update()` — it's a different kind of rule (self-protection, not role-based permission) and keeping it distinct makes the controller read as two separate, nameable guarantees instead of one method with an embedded special case.

## What's still to come

`LoanApplicationPolicy` and `LoanPolicy` land in Phase 5–8 alongside the models they govern, including the more interesting row-level rule: a Manager can approve any application, a Loan Officer can only *recommend* on applications assigned to them, and a Customer can only view (never review or approve) their own applications.
