# Scheduled Jobs

## What it is → why we need it → how we're using it

**What it is:** Laravel's scheduler lets you define recurring tasks (`Schedule::command(...)->dailyAt(...)`) in code instead of hand-editing crontab entries on a server. One real cron entry (`* * * * * php artisan schedule:run`, running every minute) delegates to whatever's actually due according to the schedule defined in `routes/console.php`.

**Why we need it:** Nothing in LendFlow's HTTP-driven flow has any natural reason to notice that a due date has quietly passed. A customer who simply doesn't visit the API that day generates no request that could trigger an overdue check. Something has to run on its own, driven by the calendar rather than by a user action — that's what the scheduler is for.

**How we're using it:** `loans:mark-overdue` runs daily at 00:15, wrapping `OverdueCheckService`: find unpaid installments past their due date, apply a penalty, mark the installment (and, the first time, its loan) overdue, and let the existing event system (`LoanMarkedOverdue` → whatever Phase 10 listeners choose to attach to it) take it from there.

## Why 00:15, not midnight exactly

An installment is considered overdue once its `due_date` (a calendar date, not a timestamp) is in the past relative to "today." Running the check at exactly `00:00:00` risks a razor-thin race with clock/timezone edge cases around what "today" means at the exact instant of rollover; 00:15 gives a small, harmless buffer with no real downside, since nothing about this check is time-sensitive down to the minute.

## Safe to run repeatedly — the actual mechanism, not just a claim

The project brief requires this specifically: *"The scheduled command should be safe to execute repeatedly without corrupting state."* Two layers make that true here, and they're independent of each other:

1. **`withoutOverlapping()` + `onOneServer()`** in `routes/console.php` — prevents two instances of the command from running at the same time at all (useful once this runs across multiple app-container replicas, where every replica would otherwise fire its own copy at 00:15).
2. **The actual idempotency guard inside `OverdueCheckService`**, which is what makes re-running the command safe even if #1 weren't there. Every installment this job might process is selected with `WHERE penalty_due = 0` — not `WHERE status != 'overdue'`. That distinction matters: a customer making a partial payment against an already-overdue installment resets its `status` to `partially_paid` (see `RepaymentService`), so guarding on status alone would let a second run re-penalize an installment that was already penalized once. `penalty_due` is set exactly once and never touched again for that installment, so it's the correct "have I already handled this" flag — status changes around it, this doesn't.

Additionally, each installment is processed in **its own transaction**, with a row lock re-acquired at the start (`RepaymentSchedule::where(...)->lockForUpdate()`) and a second check of `penalty_due` right after acquiring it. This covers the case a repayment lands on the exact same installment at the exact same moment this job reaches it — whichever transaction gets there first wins, and the other correctly sees the guard already tripped and does nothing.

## Why per-installment transactions, not one transaction for the whole batch

If the entire job ran as a single database transaction, one bad row (a constraint violation, an unexpected data state) would roll back — and therefore discard — every correctly-processed installment before it in the same run. Per-installment transactions mean a single problem row gets caught, reported (`report($e)`), and skipped, while every other loan in the batch is still correctly processed. The next scheduled run will pick up whatever failed, once whatever caused the failure is fixed.

## Penalty calculation

`OverdueCheckService`'s penalty rule follows the same philosophy as `CreditAssessmentService`: deterministic, named constants, easy to verify by hand — `max(100, 2% of the installment's remaining total)`. See `docs/repayment-allocation.md` for how a penalty is then prioritized (paid off first) once a repayment does arrive against an overdue installment.
