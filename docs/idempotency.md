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

## Where it's enforced

`EnsureIdempotencyKey` (the `idempotency` route middleware) owns the entire lifecycle — not just an "is the header present" check. It calls `IdempotencyService::handle()` itself, wrapping the *rest of the request pipeline* (`$next($request)` — which is what triggers FormRequest resolution, validation, the Policy check, and the controller) as the operation that executes at most once. Applied to `POST /loans/{id}/disburse` and `POST /loans/{id}/repayments`.

That placement — in middleware, wrapping the whole pipeline, rather than inside the controller — isn't an arbitrary preference. It's the fix for a real bug that existed when it was the other way around.

## A bug this design fixes, and why the fix has to live in middleware

An earlier version of this code ran the idempotency check *inside the controller*, after `StoreRepaymentRequest` had already validated the request. That's a real, non-obvious bug: `external_reference` carries a `unique` validation rule (mirroring the DB constraint of the same name). On a genuine retry — same `Idempotency-Key`, same `external_reference`, because the client is resending the exact request that already succeeded — validation ran *first*, saw that `external_reference` already existed (because the first attempt had just created it), and rejected the request with `422 "external reference already taken"` before the idempotency logic ever got a chance to recognize it as a replay. The one guarantee this whole feature exists to provide — *a retried request gets the original response, not an error* — silently failed for exactly the requests most likely to be retried.

The fix is structural, not a special case bolted onto validation: `EnsureIdempotencyKey` runs *before* Laravel resolves and validates a FormRequest at all — that resolution only happens once `$next($request)` is called, since that's how the framework's DI container builds the controller method's arguments. So the middleware hashes the **raw** request body (`$request->all()`), not `$request->validated()` — validation hasn't happened yet at this point in the pipeline. If it recognizes a duplicate, it returns the cached response immediately, without ever calling `$next($request)`. Validation, authorization, and the controller simply don't run a second time on a replay. There's no special-case code anywhere that says "except for external_reference" — the fix works because the check happens early enough that the conflicting validation rule never gets the chance to fire.

`tests/Feature/Repayments/RepaymentTest.php` has a direct regression test for this: a repayment with an `external_reference`, retried with the same key and the same reference, must return `201` (replayed) — not `422`. A companion test confirms the fix doesn't quietly disable the uniqueness rule itself: a *different* idempotency key reusing someone else's `external_reference` still correctly fails validation, because that's not a replay of the same attempt at all — it's two different customers' payments genuinely colliding on a reference number, which should still be rejected.

## What "resolve the existing record" means

When a duplicate key is detected, three outcomes are possible:

- **Same key, same request payload, and the original request already finished** → replay the stored response (`response_status` / `response_body`) verbatim. The client gets exactly the response the first request produced, marked with an `Idempotent-Replayed: true` header.
- **Same key, but a different request payload** → `IdempotencyConflictException` (409). The key is meant to identify one specific logical operation; silently returning an unrelated result for a mismatched body would be worse than an error.
- **Same key, original request still in flight** → `IdempotencyKeyInUseException` (409). This is the genuinely-concurrent case: the first request hasn't committed yet, so there's no stored response to replay. The client should retry shortly, once the original request has finished (successfully or not).

Payloads are hashed with keys sorted first (`ksort()` before `json_encode()`), so the hash is independent of the order fields happened to arrive in — worth doing once the hashed payload is raw client input rather than Laravel's rules-ordered `validated()` output, which was always deterministically ordered on its own.

## Why a failed operation deletes its idempotency record

If the wrapped operation throws (a validation failure, an `InvalidStateTransitionException`, anything), `handle()` deletes the `processing` record before re-throwing. Otherwise, a single failed attempt would permanently block any future retry with that key — leaving the client with no way to recover except manufacturing a brand-new key, which defeats the purpose. A failed attempt should be retryable, and it is, because it leaves no trace behind.

This still works correctly now that the operation wraps the whole HTTP pipeline rather than just a service call: an exception thrown by `StoreRepaymentRequest`'s validation, or by `RepaymentService::create()`, propagates as a real PHP exception up through `$next($request)` — Laravel's per-middleware `handle()` methods don't catch exceptions and convert them to responses; only the framework's outermost exception handler does, well above this middleware. So the exception reaches `IdempotencyService::handle()`'s `catch` block exactly as before, the processing record is deleted, and the exception continues propagating up to `bootstrap/app.php`'s registered renderers to produce the correct error response.
