# Transactions & the Ledger

## Why not just `loans.outstanding_balance`?

A single mutable balance column can only ever tell you the *current* number — it can't tell you how the institution got there, can't be reconciled against anything, and a bug that corrupts it (or a bad migration, or a manual DB fix) leaves no trace of what the correct value should have been. For a lending platform, "why is this loan's balance what it is" needs to be an answerable question, not an act of faith in application code.

So LendFlow keeps two representations, deliberately:

- **`loans.outstanding_principal` / `outstanding_interest`** — a fast, denormalized read of the current state, always derived from ledger activity.
- **`transactions` + `ledger_entries`** — the append-only, auditable source of truth. Every financial event that ever happened to a loan is reconstructable from this pair of tables alone.

## Transactions are immutable

`Transaction::UPDATED_AT = null` — Eloquent will never touch a transaction row after it's created, and no service in the codebase calls `Transaction::update()`. A mistake (an overpayment, a failed external payment that needs undoing) is corrected by posting a new `reversal`-type transaction that references what it's undoing, not by editing history. This is the same principle as an accounting ledger in the physical-paper sense: you post a correcting entry, you don't erase the original one.

## Double-entry, via `LedgerService::post()`

Every transaction is accompanied by two or more `ledger_entries` rows whose debits equal their credits — `LedgerService::post()` checks this (`assertBalanced()`) before writing anything, and it's the *only* place in the codebase allowed to create `Transaction` or `LedgerEntry` rows. That single chokepoint is what makes "is the ledger internally consistent" a question you can answer by reading one function, rather than auditing every service that might touch money.

**Disbursement**, concretely:

```
Debit  loan_principal_receivable   50,000   (the institution is now owed this)
Credit cash                        50,000   (cash leaves the institution)
```

**Repayments** (Phase 8) will follow the same shape in reverse — debit cash, credit the receivable — split proportionally between principal and interest per the allocation strategy documented in `docs/repayment-allocation.md` once that phase lands.

## `account_code`, not `account_id`

The original spec sketch named this column `account_id`, which implies a foreign key to some `accounts` table. LendFlow doesn't model a full chart of accounts — that's real scope for a production general ledger, not for a project meant to demonstrate transactional mechanics. `account_code` is a small, fixed set of named strings (`App\Support\LedgerAccounts`) instead — honest about what it actually is, and a typo in an account code becomes a broken class constant reference (a mistake you'd catch immediately) rather than a silently-accepted arbitrary integer.

## The atomicity invariant

The project brief's requirement — *there must never be a state where `Loan.status = active` but the disbursement transaction wasn't recorded* — is enforced structurally, not by convention: `LoanDisbursementService::disburse()` wraps the status change and the `LedgerService::post()` call in the same `DB::transaction()`. If the ledger write fails for any reason, the status change rolls back with it. There is no code path that updates loan status and *then* attempts to write the ledger entry as a separate step.
