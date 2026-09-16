# Security

This is a checklist-style document because that's the most useful shape for it: each item names the concrete mechanism in this codebase, not a generic best-practice statement.

## Authentication & session security
- JWT access tokens, 15-minute lifetime; refresh tokens tracked server-side, hashed, with rotation-on-use and reuse-detection. Full reasoning in `docs/authentication.md`.
- Passwords hashed with `bcrypt` (Laravel's `Hash` facade default). Minimum length 10, mixed case, numbers required (`RegisterRequest`).
- Logout revokes every refresh token for the user, not just the presented one.

## Authorization
- Every resource-level rule lives in a Policy (`docs/authorization.md`); zero `if ($user->role === ...)` checks in controllers. `grep -r "role ===" app/Http/Controllers` returns nothing.
- Row-level visibility (who sees which rows in an index) is explicit query scoping in controllers, kept deliberately separate from the yes/no Policy decisions — see `docs/authorization.md` for why conflating the two makes both harder to reason about.
- An admin cannot modify their own account through the admin user-management endpoint (`UserPolicy::modifyOwnAccount`) — a self-protection rule distinct from role-based permission.

## Mass-assignment protection
- Every Eloquent model declares an explicit `$fillable` array — no `$guarded = []`. Nothing is ever written to a model from raw, unvalidated request input; every write path goes through a Form Request's `validated()` output.

## Input validation
- Every write endpoint has a dedicated Form Request. Validation failures return `422` with a consistent, field-keyed error shape — Laravel's default behavior, not overridden.
- Numeric amounts are validated with explicit `min`/`max` bounds tied to the relevant loan product's own range, not just "must be a positive number."

## SQL injection
- 100% Eloquent query builder / parameter binding throughout — no raw SQL string interpolation anywhere in the codebase. The only raw SQL (`DB::statement(...)` calls in migrations, for `CHECK` constraints Eloquent's schema builder doesn't expose) contains no user input at all — it's fixed DDL, run once at migration time.

## Rate limiting
- Laravel's default API throttle (`$middleware->throttleApi()` in `bootstrap/app.php`) applies to every route in the `api` middleware group.

## Sensitive data handling
- `User::$hidden` excludes `password` and `remember_token` from every JSON response — enforced at the model level, not just "remembered to leave it out of the Resource" (which is also true, but the model-level hide is the actual backstop).
- Refresh tokens are stored as SHA-256 hashes, never in plaintext — a database leak alone cannot be used to impersonate a user via a stolen refresh token.
- `docs/authentication.md`'s login endpoint returns the same generic `invalid_credentials` error whether the email doesn't exist or the password is wrong — doesn't leak which one.

## Secure file uploads
- No file upload endpoints exist in this MVP — there is nothing to secure here yet. If one were added (e.g. ID document verification), it would need: server-side MIME-type verification (not trusting the client-provided `Content-Type`), a randomized storage filename (never the user-supplied name), and storage outside the public webroot with access mediated by a signed, time-limited URL rather than a public URL.

## CORS
- `config/cors.php` restricts allowed origins to `FRONTEND_URL` (the Vue SPA's origin) — not `*`. `supports_credentials` is `false`, consistent with the JWT-in-header (not cookie) auth model, which needs no CORS credential exception.

## Logging
- Nothing in this codebase logs a password, a raw token, or a full card/payment credential. `RegisterRequest`/`LoginRequest` validated data that reaches a log line (e.g. an exception's context) never includes the `password` field, since Laravel's exception handler doesn't dump full request payloads by default and nothing in this app overrides that to do so.

## Financial-integrity-as-security
Several mechanisms elsewhere in this project are also security properties, not just correctness properties, and are covered in depth in their own documents rather than duplicated here: idempotency keys preventing duplicate financial operations from a replayed request (`docs/idempotency.md`), row-level locking preventing a race from being exploited to double-spend a loan balance (`docs/race-conditions.md`), and the append-only audit log providing non-repudiable traceability of every state-changing action (`docs/loan-lifecycle.md`).

## What's explicitly out of scope for this MVP
- No real email/SMS provider integration (notifications are logged, not sent) — see `docs/queues.md`.
- No 2FA/MFA.
- No IP allow-listing or device fingerprinting.
- No WAF-layer protections (assumed to sit in front of this API in a real deployment, not implemented in application code).

These are named explicitly rather than left unstated, since a security document that only lists what *is* covered is less useful than one that's honest about what isn't.
