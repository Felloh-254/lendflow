# Architecture

## Shape: a modular monolith, not microservices

LendFlow is one Laravel application, not a set of services. For a project whose entire point is demonstrating ACID guarantees, row-level locking, and ledger consistency, splitting into microservices would work directly against the goal — distributed transactions across service boundaries are a much harder (and different) problem than the one this project sets out to demonstrate, and would add operational overhead (service discovery, network failure handling) with no corresponding benefit at this scale. A well-organized monolith with clear internal boundaries gets the architectural-maturity signal across without that cost.

## Layering

```
Controller  →  Service/Action  →  Eloquent Model  →  Postgres
   ↓                ↓
Form Request     Events → Listeners → Queued Jobs (Redis)
   ↓
API Resource
```

| Layer | Responsibility | Must NOT do |
|---|---|---|
| **Controller** | Validate request shape (via Form Requests), call one service method, return an API Resource | Contain business rules, touch Eloquent directly for writes, decide authorization logic inline |
| **Service** | Orchestrate a business operation, open DB transactions, dispatch events | Know about HTTP (no `Request` objects, no status codes) |
| **Model** | Persistence, relationships, simple accessors, guarded state-transition tables (`TRANSITIONS` constants) | Contain multi-step business workflows |
| **Policy** | Answer "can this user do X to this resource?" | Contain business calculations |
| **Event/Listener** | React to something that already happened | Decide whether it *should* happen |

Every service class under `app/Services/` maps to one clearly-named business operation — `LoanApplicationService`, `CreditAssessmentService`, `LoanDisbursementService`, `RepaymentService`, `RepaymentAllocationService`, `LedgerService`, `IdempotencyService`, `OverdueCheckService` — rather than a handful of large, multi-purpose classes. The goal: any one class should be readable and testable on its own, without having to hold the whole system in your head.

## Request lifecycle, concretely

A repayment, end to end:

```
POST /loans/{id}/repayments
  → JwtAuthenticate middleware (resolves & validates the bearer token)
  → EnsureIdempotencyKey middleware (rejects if header missing)
  → RepaymentController::store()
      → StoreRepaymentRequest (validates amount/payment_method)
      → LoanPolicy::repay (authorization)
      → IdempotencyService::handle() wraps the operation
          → RepaymentService::create()
              → DB::transaction()
                  → Loan row lock (lockForUpdate)
                  → RepaymentAllocationService::allocate() (pure calculation)
                  → write repayment_schedules, loans.outstanding_*
                  → LedgerService::post() (balanced double-entry Transaction + LedgerEntry rows)
                  → Repayment row created
                  → AuditLogService::record() (same transaction)
                  → event(RepaymentReceived) / event(LoanCompleted)
                      → Listener dispatches a queued Job (after_commit-deferred)
      → RepaymentResource
```

Every arrow into a transaction stays inside it; every arrow crossing a process boundary (HTTP response, queued job) happens only after the transaction has committed.

## Why PostgreSQL over MySQL

Two concrete reasons this project specifically needed, not a general preference: `CHECK` constraints (used throughout — non-negative balances, positive amounts, valid ranges) are more fully supported in Postgres, and `SELECT ... FOR UPDATE` semantics under `READ COMMITTED` — the mechanism the entire concurrency story (`docs/concurrency.md`) depends on — are exactly the behavior this project needed to demonstrate and rely on.

## See also

`docs/database-design.md` (schema and invariants), `docs/authentication.md` (JWT), `docs/authorization.md` (RBAC), `docs/transactions.md` + `docs/concurrency.md` + `docs/race-conditions.md` + `docs/deadlocks.md` (the financial-integrity core of this project), `docs/idempotency.md`, `docs/loan-lifecycle.md`, `docs/repayment-allocation.md`, `docs/events.md` + `docs/queues.md`, `docs/scheduler.md`, `docs/security.md`, `docs/testing.md`.
