# Authentication

## What it is → why we need it → how we're using it

**What it is:** JWT (JSON Web Token) authentication — the client holds a signed, self-contained token proving who it is, sent as `Authorization: Bearer <token>` on every request, instead of the server holding a session and the client holding a cookie.

**Why we need it:** LendFlow is API-first and will eventually be consumed by a separate Vue SPA, and potentially other clients (a mobile app, a third-party integration) that don't share a domain with the API and don't want to deal with cookies and CSRF tokens. A stateless, portable token fits a decoupled-client architecture better than a server-side session tied to one domain.

**How we're using it:** `tymon/jwt-auth`, with a deliberate asymmetry between the two tokens it issues:

## Access tokens: short-lived and fully stateless

- 15-minute lifetime (`JWT_TTL`), carrying `sub` (user id) and a `role` custom claim.
- Verified on every protected request by `JwtAuthenticate` middleware, which resolves and validates the token, checks the user's account is still `active`, and attaches the resolved user to the request.
- **Cannot be revoked before expiry.** This is the standard tradeoff of a stateless token: verifying it requires no database lookup (good for latency and horizontal scaling), but there's no way to invalidate one early short of maintaining a blacklist. We accept this tradeoff specifically *because* the window is short (15 minutes) — the blast radius of an access token that can't be revoked immediately is bounded by how quickly it expires on its own.

## Refresh tokens: long-lived, but tracked server-side

This is the part that isn't "just default JWT behavior," and it's the actual interesting design decision here: **refresh tokens are NOT purely stateless.** Each one is a random opaque string, hashed (SHA-256, same rationale as password hashing — a database leak shouldn't hand out usable tokens) and stored in a `refresh_tokens` table with an `expires_at` and a `revoked_at`.

Why: a pure stateless refresh token has the same non-revocable property as the access token, but over a *much* longer window (14 days by default) — an unacceptable blast radius for a token that can mint fresh access tokens indefinitely. Tracking refresh tokens server-side means they genuinely can be revoked, which matters for two concrete cases:

1. **Explicit logout** — `POST /auth/logout` revokes every refresh token for that user, not just invalidating the presented access token. Without server-side tracking, a stolen refresh token would keep working after the legitimate user "logged out."
2. **Detected theft (reuse of an already-rotated token)** — see below.

## Rotation-on-use and reuse-detection

Every successful `POST /auth/refresh`:
1. Validates the presented refresh token (exists, not expired, not already revoked).
2. **Immediately revokes it** and issues a brand-new refresh token in its place.
3. Returns the new access/refresh pair.

If the *same, already-rotated* refresh token is ever presented again, that's treated as a strong signal of theft — an attacker who copied a token before it was rotated, now trying to use their stale copy. The response: **every refresh token for that user is revoked**, forcing a full re-login everywhere. A legitimate client, having received the rotated token from the original response, would never present the old one again — so this can only happen if two parties had the same token, which should never occur under normal use.

This is implemented in `RefreshTokenService::rotate()`, inside a single DB transaction with a row lock on the refresh token being consumed — the same reasoning as every other "read then act" operation in this codebase (see `docs/concurrency.md`): without the lock, two near-simultaneous refresh attempts with the same token could both pass validation before either revokes it.

## Tradeoffs versus Laravel session authentication

| | JWT (this project) | Laravel sessions |
|---|---|---|
| Client type | Any (SPA, mobile, third-party) | Same-site browser client |
| CSRF protection | Not needed (no cookie) | Required, built-in |
| Revocation | Access: no (bounded by short TTL). Refresh: yes (tracked) | Immediate (session store) |
| Scaling | Stateless verification, no shared session store needed | Needs a shared session store (Redis, DB) across app servers |
| Complexity | Token lifecycle (expiry, rotation, blacklist) is explicit application concern | Handled by the framework almost entirely |

Sessions would have been simpler to implement and would get CSRF protection for free — but they assume a browser client sharing a domain (or at least a scheme Laravel's session cookies support), which doesn't fit "API-first, eventually consumed by a separate SPA and potentially other clients." JWT is the right tool for that shape of system; it is not a universally "better" choice than sessions, and this project's `docs/security.md` is explicit about the tradeoff rather than treating JWT as a strictly superior default.

## Password hashing

Laravel's default `Hash::make()` (bcrypt) — no custom hashing scheme. Nothing about this project's requirements justifies deviating from Laravel's well-reviewed default.
