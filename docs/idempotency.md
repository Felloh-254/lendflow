# Idempotency

## The problem

`POST /loans/{id}/disburse` moves real money. Consider a manager's client that sends the disbursement request, but the response never arrives — a network blip, a timeout, a proxy that drops the connection. The manager has no way to know whether the disbursement happened. If they retry the same request and the server just executes it again, the loan gets disbursed twice: two ledger transactions, cash leaving the institution twice for one loan.

An `Idempotency-Key` header lets the client say, explicitly: *this retry is the same logical operation as before, not a new one.* The server's job is to guarantee that operation executes at most once, no matter how many times the client sends it — and to give the retry the exact same response the original request would have produced, so the client can't tell the difference between "it worked the first time" and "it's being replayed."

## Why this can't be a SELECT-then-INSERT check

A naive implementation — "look up whether this key exists; if not, run the operation and save the key" — has the exact same race condition shape as the concurrent-repayment bug documented in `docs/concurrency.md`: two requests with the same key can both pass the SELECT before either has written anything, and both proceed to execute the operation.

`IdempotencyService::handle()` avoids this by making the *very first* thing it does an `INSERT`, not a `SELECT`. The `idempotency_keys` table has a `UNIQUE` constraint on `(key, endpoint, user_id)`. When two requests race:

1. Both attempt to `INSERT` a new idempotency record.
2. PostgreSQL allows exactly one of those inserts to succeed; the other fails immediately with a `23505 unique_violation` error.
3. The request whose insert succeeded proceeds to run the operation.
4. The request whose insert failed catches that specific error and looks up the existing record to decide what to do — it never runs the operation.

There's no window between "check" and "act" for both requests to slip through, because the database's uniqueness guarantee *is* the check-and-act — it's atomic by construction, not by careful sequencing of application code.

## What "resolve the existing record" means

When a duplicate key is detected, three outcomes are possible:

- **Same key, same request payload, and the original request already finished** → replay the stored response (`response_status` / `response_body`) verbatim. The client gets exactly the response the first request produced, marked with an `Idempotent-Replayed: true` header.
- **Same key, but a different request payload** → `IdempotencyConflictException` (409). The key is meant to identify one specific logical operation; silently returning an unrelated result for a mismatched body would be worse than an error.
- **Same key, original request still in flight** → `IdempotencyKeyInUseException` (409). This is the genuinely-concurrent case: the first request hasn't committed yet, so there's no stored response to replay. The client should retry shortly, once the original request has finished (successfully or not).

## Why a failed operation deletes its idempotency record

If the wrapped operation throws (a validation failure, an `InvalidStateTransitionException`, anything), `handle()` deletes the `processing` record before re-throwing. Otherwise, a single failed attempt would permanently block any future retry with that key — leaving the client with no way to recover except manufacturing a brand-new key, which defeats the purpose. A failed attempt should be retryable, and it is, because it leaves no trace behind.

## Where it's enforced

- The `EnsureIdempotencyKey` middleware (`idempotency` route alias) rejects a request outright with `400 idempotency_key_missing` if the header isn't present at all — a cheap, early check before anything else runs.
- `IdempotencyService::handle()` does the actual work, wrapping the real operation as a callback so it executes at most once.
- Currently applied to `POST /loans/{id}/disburse`; `POST /loans/{id}/repayments` gets the same treatment in Phase 8.
