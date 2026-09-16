# Concurrency Strategy

## The invariant that matters

A loan's outstanding balance must never be consumed twice. Two repayment requests against the same loan, arriving at (near) the same instant, must not both be allowed to succeed if only one payment's worth of balance actually exists. This document is the strategy; `docs/race-conditions.md` is the actual reproduction and fix; `docs/deadlocks.md` covers the other concurrency failure mode this project demonstrates.

## The mechanism: row-level locking, not application-level checking

`RepaymentService::create()` does this, in order, inside one `DB::transaction()`:

```php
$locked = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();
$outstanding = $locked->totalOutstanding();
if ($amount > $outstanding) { throw InsufficientOutstandingBalanceException::make(...); }
// ... allocate, write schedule rows, write the loan's new outstanding balance ...
```

`lockForUpdate()` compiles to PostgreSQL's `SELECT ... FOR UPDATE`. The first transaction to reach this line acquires an exclusive row lock on that specific loan. A second, concurrent transaction attempting the same `lockForUpdate()` call on the same row **blocks** — it does not proceed, does not read a value, does not do anything — until the first transaction commits or rolls back. Only then does it acquire the lock and read the row, and by that point it sees the balance the first transaction actually left behind, not a stale value from before that transaction ran.

This is the difference between "checking a balance" (which can always be stale by the time you act on it) and "locking a balance before checking it" (which makes staleness structurally impossible for the duration of the lock).

## Why `READ COMMITTED`, not `SERIALIZABLE`

PostgreSQL's default isolation level, `READ COMMITTED`, is what LendFlow uses everywhere — combined with explicit `lockForUpdate()` calls on the specific rows that matter. `SERIALIZABLE` is a stronger, more expensive isolation level that detects a broader class of anomalies automatically, at the cost of throughput and the need for applications to retry on serialization failures that can occur even on operations that don't obviously conflict.

For LendFlow's actual access patterns — every hot-path transaction touches exactly one loan row it explicitly locks — `SERIALIZABLE` would buy nothing `lockForUpdate()` doesn't already guarantee, while adding retry-handling complexity throughout the codebase for a class of anomaly (e.g. phantom reads across unrelated rows) that doesn't apply here. Reaching for the strictest available isolation level "to be safe" without being able to say what specific anomaly it prevents *for this workload* is a cost paid for its own sake — the right call is to identify the exact contention point (a loan's balance) and lock precisely that.

## Where locking is and isn't used

| Operation | Locks | Why |
|---|---|---|
| `RepaymentService::create()` | One `Loan` row | The core race — see docs/race-conditions.md |
| `LoanDisbursementService::disburse()` | One `Loan` row | Prevents double-disbursement under a retried/duplicate request without an idempotency key |
| `RefreshTokenService::rotate()` | One `RefreshToken` row | Prevents a raced refresh from both rotating the same token successfully |
| `LoanApplicationService` (submit/assess/approve/reject) | None | Single-row status update with a transition check; no read-then-write balance calculation exists here, so there's no analogous race to guard against |

A deliberate pattern worth naming: **every locking operation in this codebase locks exactly one row.** That's not an accident — it's the main reason LendFlow's real code paths cannot deadlock against each other (see docs/deadlocks.md for what happens when that constraint is dropped, via a dedicated demonstration).

## How this is actually tested

`docs/race-conditions.md` covers this in depth, but the short version: PHPUnit/Pest runs as a single process, so a test that "simulates" concurrency by calling a service twice in a loop proves nothing about real lock contention — it never generates genuine concurrent access. LendFlow's concurrency tests instead spawn real, independent `php artisan` child processes (via Symfony Process) against the same test database, so the row-locking behavior under test is the real PostgreSQL behavior, not a mocked approximation of it.
