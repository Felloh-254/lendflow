# Database Design

## Entity relationship overview

```
users 1───1 customers
users 1───* credit_assessments (assessed_by)
users 1───* audit_logs
users 1───* refresh_tokens

customers 1───* loan_applications
customers 1───* loans
customers 1───* repayments

loan_products 1───* loan_applications
loan_products 1───* loans

loan_applications 1───1 credit_assessments
loan_applications 1───1 loans

loans 1───* repayment_schedules
loans 1───* repayments
loans 1───* transactions

transactions 1───* ledger_entries
repayments *───1 transactions (nullable)

idempotency_keys — standalone, keyed on (key, endpoint, user_id)
audit_logs — standalone, entity referenced by (entity_type, entity_id) as plain strings, not a DB-level polymorphic relation
```

Full column-by-column listings live in the migrations themselves (`database/migrations/`), which are the actual source of truth — this document covers the *decisions*, not a duplicate field list that could drift from them.

## Which invariants belong at the database level, and why

The project brief asks this directly, so here's the concrete answer, not just the general principle:

| Invariant | Enforced by | Why not application-only |
|---|---|---|
| Unique email, national ID, phone | DB unique constraint | A race between two concurrent registrations with the same email must not both succeed — see the same class of race `docs/race-conditions.md` covers for repayments |
| Unique transaction reference, external payment reference, idempotency key | DB unique constraint | These specifically exist to prevent duplicates *under concurrency* — an application-level "check then insert" has the exact race condition these constraints exist to close |
| `outstanding_principal >= 0`, `outstanding_interest >= 0`, `outstanding_fees >= 0` | DB check constraint | Last line of defense: if a bug in `RepaymentService` ever computed a negative balance, the constraint turns that into a loud `500` at write time, not a silently-corrupted balance discovered later |
| `min_amount <= max_amount`, `term_min <= term_max` on loan products | DB check constraint | Holds regardless of which code path writes the row — a future admin UI, a seeder, a manual fix — not just the one Form Request that happens to check it today |
| Foreign keys everywhere (customer→user, loan→customer, repayment→loan, etc.) | DB foreign key | Referential integrity that must hold regardless of which code path writes the row, same reasoning as above |

The general rule this project follows: **application-level validation is for good error messages; database constraints are for invariants that must hold no matter what wrote the row.** Form Requests give a customer a clear "amount must be between X and Y" message. The database constraint behind it is what guarantees that invariant can never be violated by a bug, a race condition, a future code path, or a direct SQL fix run by someone in a hurry.

## Why some things are denormalized

`loans.outstanding_principal` / `outstanding_interest` / `outstanding_fees` are a **denormalized, fast-read cache** of what the ledger (`transactions` + `ledger_entries`) already implies — not the source of truth. `docs/transactions.md` covers this in depth: the ledger is append-only and auditable; the loan's outstanding columns exist so a `GET /loans/{id}` doesn't need to sum every ledger entry for that loan on every read. They're kept in sync by being written in the exact same transaction as the ledger entries that justify the new value — never independently.

## Why `credit_assessments` overwrites instead of appending

Covered in full in `docs/loan-lifecycle.md` — short version: only the *latest* assessment is ever actionable, so a `unique` constraint on `loan_application_id` (upsert semantics) is simpler than an append-only history table for a value nothing in this MVP needs the history of.

## Naming deviations from the original spec sketch, and why

Two fields were deliberately renamed from an early sketch of this schema, both documented at the point of deviation:

- `ledger_entries.account_code` (not `account_id`) — there's no `accounts` table in this project; a numeric "id" would misleadingly imply a foreign key that doesn't exist. See `docs/transactions.md`.
- `transactions.loan_id` was **added** beyond the original field list — every transaction type here is loan-scoped, and querying "all transactions for this loan" is a core need that would otherwise require joining through `repayments`, which doesn't even apply to disbursement or fee transactions.
