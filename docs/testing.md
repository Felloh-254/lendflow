# Testing

## Framework and why

Pest, on top of PHPUnit (Pest is a thin, expressive syntax layer — every Pest test is a valid PHPUnit test underneath). Chosen because the scenario-based style (`it('does X', function () { ... })`) reads closer to the plain-English behavior being verified than PHPUnit's class/method-name convention, which matters more than usual here given how much of this project's value is in the *reasoning* behind each test, not just the assertion.

## Why tests run against real PostgreSQL, not SQLite

`phpunit.xml` points every test run at a dedicated `lendflow_testing` Postgres database — not the in-memory SQLite that's the path of least resistance for a Laravel test suite. This is a deliberate, load-bearing choice: SQLite's locking model is materially different from Postgres's `SELECT ... FOR UPDATE` semantics, and a huge fraction of what this project sets out to prove (row-level locking preventing lost updates, real deadlock detection) simply doesn't exist as a phenomenon on SQLite. A test suite that passed against SQLite would prove nothing about the concurrency claims this project makes; it would just be exercising business logic against a database that can't produce the failure modes being guarded against.

## Coverage by category

**Authentication** (`tests/Feature/Auth/`) — registration validation (including the 18+ age check), login (including not leaking whether an email exists), protected-route access, refresh rotation and reuse-detection, logout.

**Authorization** (`tests/Feature/Admin/`, `tests/Feature/Customers/`, `tests/Feature/LoanApplications/…AuthorizationTest.php`) — every role boundary asserted both ways: the role that *should* be able to do something can, and every other role gets `403`. Includes row-level cases (a customer can't view another customer's application; a loan officer can't assess an application already claimed by a colleague).

**Loan lifecycle** (`tests/Feature/LoanApplications/`, `tests/Feature/Loans/`) — the full happy path (draft → submit → assess → approve → disburse → repay → complete) and, separately, every illegal transition (approving a draft, re-approving an approved application, cancelling an approved loan) asserted to fail with `409 invalid_state_transition`.

**Financial integrity** — repayment allocation math verified against hand-computable numbers (`docs/repayment-allocation.md`'s worked example is a real test, not just documentation), ledger balance assertions (`sum(debits) == sum(credits)`) after every disbursement/repayment/penalty, the insufficient-balance rejection, and rollback-on-failure (an approval that throws mid-transaction leaves no loan, no audit row, no queued job — `Queue::assertNothingPushed()`).

**Concurrency** (`tests/Feature/Concurrency/`, tagged `@group concurrency`) — the one category that doesn't fit the "single PHPUnit process" model at all, and is built differently on purpose: real, independent OS processes (`php artisan repayment:simulate` / `deadlock:simulate`) against the same live database, proving actual PostgreSQL lock contention rather than a mocked approximation of it. See `docs/race-conditions.md` and `docs/deadlocks.md` for the full reasoning; run them on their own with `./vendor/bin/pest --group=concurrency` since they're deliberately slower (each holds a transaction open for up to ~1.5s to guarantee real overlap between processes).

**Idempotency** (`tests/Feature/IdempotencyServiceTest.php`, plus coverage embedded in the disbursement/repayment tests) — replay of an identical request, conflict on a reused key with a different body, the in-progress-request case, and retry-ability after a failed attempt.

## What's deliberately NOT tested here

No browser/E2E tests (no frontend exists yet — the Vue dashboard is a later phase). No load/performance testing beyond the concurrency suite's 10-simultaneous-requests case, which tests *correctness* under concurrency, not throughput. No mutation testing or coverage-percentage gate — for a project this size, coverage of the specific behaviors listed above is a more meaningful signal than a percentage target.
