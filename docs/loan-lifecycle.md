# Loan Application Lifecycle

## States and transitions

```
draft ──submit──> submitted ──assess──> under_review ──approve──> approved
  │                   │                       │
  └──cancel──>   cancel                   reject
                      │                       │
                      v                       v
                  cancelled               rejected
```

The full transition table lives in `LoanApplication::TRANSITIONS` — one array, checked by every write to `status` via `LoanApplicationService`. No controller or model method sets `status` directly; `LoanApplication::canTransitionTo()` is the single source of truth for "is this move legal right now", and `LoanApplicationService::transition()` is the single place that checks it before writing.

Illegal transitions (approving a draft, cancelling an approved loan, approving an already-approved application) throw `InvalidStateTransitionException`, rendered as `409 Conflict` with `error.code = invalid_state_transition` — never a silent no-op, never a generic 500.

## Assignment: how a loan officer gets an application

There's no separate "assign" endpoint. The first loan officer to call `POST /loan-applications/{id}/assess` on an unassigned application becomes its `assigned_loan_officer_id`, atomically, in the same transaction as the assessment itself. From that point, `LoanApplicationPolicy::assess()` only allows that same officer (or, for an application that's still unassigned, any officer) to assess it — a colleague gets a `403`, not a silent overwrite.

This was a deliberate simplification for the MVP: it avoids a whole extra workflow (a manager or a round-robin system explicitly assigning applications) while still giving every application a single accountable reviewer once work has actually started on it. A real institution would likely want deliberate assignment (workload balancing, specialization by loan type) — that's a natural extension point, not a limitation baked into the schema.

## Why credit assessment is "assess" (upsert), not an immutable history

`credit_assessments` has a `unique` constraint on `loan_application_id` — assessing twice **overwrites** the existing row rather than creating a new one. Only the latest assessment is ever actionable (a manager approves or rejects based on the current numbers), so a history of prior assessments isn't needed for the application to function correctly.

This is a genuine tradeoff, not an oversight: if "show me how this applicant's risk profile changed over three assessments" became a real requirement, the fix is to switch this table to an append-only pattern (like `audit_logs`) and read the *latest* row by `created_at` instead of relying on uniqueness. Right now, `audit_logs` already captures *that* a re-assessment happened and what changed — just not the full numeric assessment payload each time.

## What "existing debt" means right now

`CreditAssessmentService::assess()` is currently called with `existingDebt: 0.0` — LendFlow doesn't yet track a customer's other active loans (that's the `loans` table, landing in Phase 6). Once it exists, `assess()` will sum a customer's outstanding balances across their other active loans and pass that in. The scoring service itself doesn't need to change — it already accepts `existingDebt` as a parameter — only the caller does.

## Audit trail

Every transition (`created`, `submitted`, `assessed`, `approved`, `rejected`, `cancelled`) writes one `audit_logs` row via `AuditLogService`, inside the same DB transaction as the change itself — so an audit entry only exists if the change it describes actually committed. Each entry records the actor, the before/after values that changed, and the request IP.
