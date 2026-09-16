# Queues

## What it is → why we need it → how we're using it

**What it is:** A queue lets a unit of work be handed off to a background worker process instead of executed inline during an HTTP request. Laravel serializes the job, pushes it onto a backend (Redis, here), and a separate `queue:work` process picks it up and runs it whenever it gets to it.

**Why we need it:** Some operations in LendFlow have no business making a customer or loan officer wait on them. Sending a notification email/SMS means a network call to an external provider with its own latency and its own chance of being briefly down — none of which should be able to turn "your repayment was accepted" into a slow or failed HTTP response. The financial correctness of the repayment doesn't depend on whether the confirmation message went out yet.

**How we're using it:** Four queued jobs — `SendLoanApprovalNotification`, `SendRepaymentConfirmation`, `GenerateStatement`, `ProcessAuditNotification` — each dispatched by a Listener reacting to a domain event (see `docs/events.md`). All run on the `redis` queue connection, picked up by the dedicated `queue` container defined in `docker-compose.yml` (`php artisan queue:work redis --tries=3 --backoff=5`).

## Why each one is queued instead of synchronous

| Job | Why it's queued |
|---|---|
| `SendLoanApprovalNotification` | External notification provider I/O — shouldn't block a manager's approve request |
| `SendRepaymentConfirmation` | Same — shouldn't block a customer's repayment request |
| `GenerateStatement` | No time pressure (nobody's waiting on it to render a response), and would only get heavier if extended to real PDF rendering |
| `ProcessAuditNotification` | Best-effort external notification (see docs/events.md) — losing it to a transient failure is an acceptable tradeoff for never blocking on it |

## The subtlety that actually matters: `after_commit`

Every one of these jobs is dispatched from a Listener reacting to an event fired **from inside** the DB transaction that made the change — e.g. `event(new LoanApproved($application))` sits inside `LoanApplicationService::approve()`'s `DB::transaction()` closure, right after the loan is created.

`event()` fires its listeners immediately and synchronously at the point it's called. If a listener dispatches a queued job at that point, the job gets pushed onto Redis **immediately — mid-transaction**, before `DB::transaction()` has actually committed anything. A fast queue worker could dequeue and start running `SendLoanApprovalNotification` while the surrounding transaction is still open. If that transaction then failed and rolled back for any reason, the notification job would already be executing against an application that, as far as the database is concerned, was never actually approved.

`config/queue.php` sets `'after_commit' => true` on the Redis connection specifically to close this gap. With it enabled, Laravel doesn't push a job dispatched from inside a transaction immediately — it registers the push as a callback to run after that transaction commits, and silently discards it if the transaction rolls back instead. This is a connection-level setting, not something each `Job` class has to remember to opt into (though `ShouldQueueAfterCommit` exists for a per-job override if a connection's default ever needed to be overridden the other way).

`tests/Feature/Events/DomainEventsTest.php`'s last test verifies the discard half of this directly: an approval attempt that fails validation and rolls back queues nothing at all, `Queue::assertNothingPushed()`.

## Retry semantics

Every job sets `public int $tries = 3`. Combined with `queue:work --tries=3 --backoff=5` in `docker-compose.yml`, a job that throws gets retried up to 3 times with a 5-second backoff between attempts before landing in `failed_jobs` for manual inspection — appropriate for notification-style work where a transient failure (provider briefly down) is the expected failure mode, not a reason to give up immediately.
