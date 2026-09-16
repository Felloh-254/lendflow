# Events & Listeners

## What it is → why we need it → how we're using it

**What it is:** Laravel events let a piece of code announce "something happened" (`event(new LoanApproved($application))`) without needing to know or care who, if anyone, reacts to it. Listeners subscribe to an event class and run when it fires.

**Why we need it:** Without events, `LoanApplicationService::approve()` would need to directly call every side effect of an approval — send a notification, ping a compliance channel, maybe eventually update analytics — all inline. Every new side effect would mean editing the approval service itself, and the service's job (safely transitioning application status and creating the loan) would get harder to read as unrelated concerns accumulated in it. Events let side effects register themselves without the thing that triggers them needing to change.

**How we're using it:** Four domain events — `LoanApproved`, `LoanDisbursed`, `RepaymentReceived`, `LoanCompleted` (`LoanMarkedOverdue` joins them in the Phase 11 scheduler) — each dispatched from inside the same `DB::transaction()` as the state change they describe. Listeners are deliberately thin: their only job is deciding *which* queued job an event should trigger, never doing the actual I/O themselves (see `docs/queues.md` for why that split matters).

## Where audit logging fits — and where it deliberately doesn't

`AuditLogService::record()` is called directly from inside each service, synchronously, in the same transaction as the change — **not** via an event listener. This is a deliberate exception to "listeners react to events": the `audit_logs` row is a legal/compliance record of what happened, and it has to be atomic with the change itself. If it went through the event system instead, an event listener throwing an exception, or Laravel's queued-listener mechanics deferring it, could result in a state change with no audit trail — exactly the failure mode audit logging exists to prevent.

`ProcessAuditNotification` (dispatched by `AuditNotificationSubscriber`, listening on all four events) is a *different* thing entirely: a best-effort, asynchronous ping to an ops/compliance channel. Losing that notification to a transient failure is fine — the permanent audit_logs row is unaffected either way. Conflating "the durable record" with "the notification about the record" would have made the durable record only as reliable as the queue.

## Registration

Both the `Event => Listener` map and the subscriber are registered explicitly in `AppServiceProvider::boot()`, not auto-discovered — same reasoning as the explicit Policy map in `docs/authorization.md`: one place to see every domain event this app fires and everything that reacts to it.
