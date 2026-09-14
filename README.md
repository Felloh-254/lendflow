# LendFlow

A mock digital lending platform API built in Laravel to demonstrate transactional integrity, concurrency control, idempotency, and role-based access control. See `docs/` (added as each phase lands) for the full technical writeups.

This is **not** a clone of any real institution — it's a learning/portfolio project.

## What's implemented so far (Phases 1–3)

- Docker Compose environment: `app` (PHP-FPM), `nginx`, `postgres`, `redis`, `queue`, `scheduler`
- Laravel 11 skeleton (routing, middleware, exception handling wired in `bootstrap/app.php`)
- `users`, `customers`, `refresh_tokens` migrations
- Full JWT authentication flow: register, login, refresh (with rotation + reuse detection), logout, me
- Pest feature tests covering all of the above

Everything from Phase 4 onward (RBAC/policies, loan lifecycle, disbursement, repayments, concurrency demo, deadlock demo, idempotency, queues, scheduler, OpenAPI docs) lands in subsequent responses, per the phased plan.

## Requirements

- Docker + Docker Compose
- That's it — PHP/Postgres/Redis all run in containers. You do **not** need PHP or Composer installed locally.

## Getting started

```bash
cp .env.example .env
docker compose up -d --build
```

On first boot, the `app` container's entrypoint will automatically:

1. Run `composer install`
2. Generate `APP_KEY` and `JWT_SECRET`
3. Run migrations

Once it's up:

```bash
# API is now reachable at:
curl http://localhost:8000/up   # Laravel health check

# Register a customer:
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Wanjiku",
    "email": "jane@example.com",
    "password": "StrongPass1",
    "password_confirmation": "StrongPass1",
    "phone": "254712345678",
    "national_id": "12345678",
    "date_of_birth": "1995-01-01",
    "monthly_income": 80000,
    "employment_status": "employed"
  }'
```

## Running tests

Tests run against a **real PostgreSQL database** (`lendflow_testing`), not SQLite — this matters later once the concurrency/locking test suite lands, since SQLite's locking semantics don't reflect what happens under Postgres row locks.

```bash
docker compose exec app php artisan db:create lendflow_testing   # first time only, or create manually
docker compose exec app php artisan migrate --env=testing --database=pgsql
docker compose exec app ./vendor/bin/pest
```

## Useful commands

```bash
docker compose logs -f app          # tail app logs
docker compose exec app bash        # shell into the app container
docker compose exec app php artisan migrate:fresh --seed
docker compose down -v              # tear down + wipe volumes
```

## Design decisions worth knowing about (expanded in `docs/` as each lands)

- **JWT over Laravel sessions** — API-first, stateless, multi-client (`docs/authentication.md`, coming with Phase 3 writeup).
- **Refresh tokens are tracked server-side** (hashed, in `refresh_tokens`) specifically so they *can* be revoked before expiry — a pure stateless JWT refresh token can't be. Rotation-on-use plus reuse detection covers the theft case.
- **Postgres over MySQL** — better support for check constraints and the isolation-level control the concurrency work needs.
- **`READ COMMITTED` + explicit `lockForUpdate()`**, not `SERIALIZABLE` — full reasoning lands in `docs/concurrency.md`.
