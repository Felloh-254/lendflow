# Race Conditions: Reproduction and Fix

## 1. What the race condition was

Two repayment requests against the same loan, arriving concurrently, each reading the loan's outstanding balance *before either has written anything back*. Both requests see the same (soon-to-be-stale) balance, both conclude their payment is valid against it, and both write a new balance computed from that same stale read. The second write doesn't build on the first — it overwrites it. This is a classic **lost update**.

Concretely, with a loan carrying exactly 10,000 outstanding and two simultaneous requests each paying 10,000:

- Both requests read `outstanding_principal = 10,000`.
- Both compute `10,000 - 10,000 = 0`.
- Both write `outstanding_principal = 0`.
- The institution now has **two** completed repayment records worth 10,000 each — 20,000 "collected" — but the loan balance only ever reflected one of those payments.

## 2. How it was reproduced

`tests/Feature/Concurrency/RaceConditionTest.php`, the first test, does this for real — not a simulation. PHPUnit/Pest is single-threaded, so proving a genuine race requires genuine concurrent processes: the test spawns two independent `php artisan repayment:simulate --unsafe` child processes (via Symfony Process — see `tests/Support/ConcurrentProcess.php`) against the same loan in the same PostgreSQL test database.

`--unsafe` runs a deliberately naive repayment path (`SimulateRepayment::runUnsafe()`) that mirrors the exact shape of the bug: read the balance, optionally hold (simulating a slow request), then write a balance computed from what was read — with **no row lock**. A `--hold-ms` flag lets the test hold the first process's transaction open long enough to *guarantee* the second process's read happens before the first process's write, rather than hoping OS scheduling happens to produce that overlap on any given run.

Running it produces exactly the outcome described above: both processes report success, both reads show the same stale balance, and the final state has two repayment rows but a loan balance that only reflects one payment.

## 3. Why it happened

Because the balance check and the balance write were two separate, unsynchronized operations — `SELECT` then, later, `UPDATE` — with nothing preventing a second transaction's `SELECT` from happening in the gap between the first transaction's `SELECT` and its `UPDATE`. Neither transaction had any way to know the other existed.

## 4. How it was fixed

`RepaymentService::create()` (the real, production code path — `--unsafe` only exists in the test harness) wraps the entire read-check-write sequence in one `DB::transaction()` and acquires the loan row lock **first**, via `Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail()`, before reading the balance at all:

```php
return DB::transaction(function () use ($loan, $actor, $amount, ...) {
    $locked = Loan::where('id', $loan->id)->lockForUpdate()->firstOrFail();
    $outstanding = $locked->totalOutstanding();
    if ($amount > $outstanding) {
        throw InsufficientOutstandingBalanceException::make($amount, $outstanding);
    }
    // ... allocate and write, still inside the same transaction ...
});
```

`tests/Feature/Concurrency/RaceConditionTest.php`'s second test runs the identical concurrent scenario — same loan, same 10,000 balance, same two simultaneous 10,000 requests, same `--hold-ms` timing — but against the real `RepaymentService` instead of `--unsafe`. The result: exactly one request succeeds; the other's `lockForUpdate()` call blocks until the first transaction commits, then re-reads the now-zero balance and correctly throws `InsufficientOutstandingBalanceException`. One repayment record, a loan balance of exactly zero, no lost update.

A third test goes further: ten concurrent requests of 2,000 each against a 10,000 balance. Exactly five succeed (the other five correctly fail once the balance is exhausted), and the final balance is exactly zero — never negative, regardless of the non-deterministic order in which the ten requests actually get processed.

## 5. Why this locking strategy works

`SELECT ... FOR UPDATE` (what `lockForUpdate()` compiles to) takes an exclusive lock on the selected row for the remainder of the transaction. A second transaction requesting the same lock doesn't get a stale read and doesn't get an error — it **blocks**, synchronously, until the first transaction ends. This turns "read-then-write" from two independent operations that can interleave into one indivisible unit from the perspective of any other transaction touching the same row. There is no window for a second transaction to observe a balance that's about to become invalid.

This is a stronger guarantee than an application-level check (e.g. "verify the balance hasn't changed since we read it, and retry if it has" — optimistic concurrency control) because it prevents the bad interleaving from happening at all, rather than detecting it after the fact and hoping the retry logic is correct too. For a financial operation where "detect and retry" means re-running money-moving logic, "prevent entirely" is the safer property to rely on.
