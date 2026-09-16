# Deadlocks: Reproduction and Mitigation

## What caused the deadlock

A deadlock needs two transactions that each hold a lock the other one wants, with no way for either to proceed — a circular wait. LendFlow's real code paths (disbursement, repayment) each lock exactly **one** loan row per transaction, which structurally cannot deadlock: a transaction that only ever wants one resource can't be waiting on itself.

To produce an actual deadlock, this project simulates the kind of operation that *could* introduce one if written carelessly: a hypothetical batch settlement job that needs to lock two loans in a single transaction — for example, netting a transfer between two accounts, or a batch job processing loans in whatever order a query happened to return them. `app/Console/Commands/SimulateDeadlock.php` implements exactly this shape: lock loan A, hold briefly, then lock loan B.

The deadlock occurs when two such transactions run concurrently with **opposite lock orders**:

- Transaction 1: locks loan A, then attempts to lock loan B.
- Transaction 2: locks loan B, then attempts to lock loan A.

If both transactions acquire their first lock before either attempts its second, each is now waiting on a lock the other one holds. Neither can proceed. This is the textbook precondition for a deadlock.

## How it was reproduced

`tests/Feature/Concurrency/DeadlockTest.php`'s first test spawns two real `php artisan deadlock:simulate` processes (same technique as the race-condition tests — see `docs/race-conditions.md` for why real OS processes are necessary) against the same two loans, one with `--reverse` so their lock orders conflict. Each process holds its first lock for 1 second before attempting the second, which guarantees both processes are mid-wait at the same time rather than relying on scheduling luck.

The result: PostgreSQL's own deadlock detector identifies the circular wait and aborts one of the two transactions with `SQLSTATE 40P01` (`deadlock_detected`). The aborted transaction's error is caught and reported; the other transaction proceeds and commits normally.

```json
{"success": false, "deadlock_detected": true, "locked_order": [12, 7]}
{"success": true, "locked_order": [7, 12]}
```

Which of the two transactions gets chosen as the "victim" is decided by PostgreSQL's internal deadlock detection algorithm, not by application code — and application code shouldn't rely on it being one or the other.

## How it was resolved

Two independent mitigations, both demonstrated with dedicated tests:

**1. Consistent lock ordering (prevention).** If every transaction that needs to lock multiple rows always acquires those locks in the same global order — here, ascending loan ID — a circular wait becomes impossible. Whichever transaction gets there first acquires the lower-ID lock; the other one simply waits its turn for that same lock, then proceeds once it's released. That's contention (a brief wait), not deadlock (a permanent one). `SimulateDeadlock --consistent-order` implements this by sorting the two loan IDs before locking, ignoring the requested order. The test suite's second test runs the *exact same contention pattern* as the broken test — same loans, same timing — and both processes succeed.

**2. Bounded retry with backoff (recovery).** For cases where consistent ordering alone isn't practical — the set of resources to lock isn't known upfront, for instance — `app/Support/RetriesOnDeadlock.php` provides a small trait that catches `SQLSTATE 40P01` specifically, applies a short jittered backoff, and retries the whole operation from scratch (a fresh transaction, not a resumed one — the failed transaction was already fully rolled back by Postgres). The suite's third test runs the same inconsistent-order setup that reliably deadlocks, but with `--retry` — the victim transaction retries and succeeds on a subsequent attempt, since by then the "winning" transaction has already committed and released its locks.

Prevention is preferred where it's available — it costs nothing at runtime and makes the failure mode impossible rather than merely recoverable. Retry is the fallback for the cases prevention can't cover.

## What would happen in production

An undetected/unmitigated deadlock isn't actually possible with PostgreSQL — the database itself always detects the cycle and aborts one participant, typically within its `deadlock_timeout` setting (1 second by default). The real production risk isn't a permanently stuck system; it's an **unhandled exception** reaching the user as a raw 500 error on an operation that could have succeeded if it had simply been retried, plus the operational noise of deadlock errors showing up in logs for something that a `RetriesOnDeadlock`-wrapped operation would have resolved silently. For a financial API, "the request failed and the user has to try again" is a worse experience than "the request took an extra 50ms because it silently retried once" — which is exactly what mitigation 2 above buys, for the specific class of operation where prevention isn't already sufficient.
