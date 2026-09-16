# Repayment Allocation

## The question this answers

A repayment amount rarely lines up exactly with what's due. A customer might pay less than an installment (partial payment), exactly one installment, or enough to cover several installments at once. `RepaymentAllocationService::allocate()` is the single place that decides, given a loan and an amount, *which* installments absorb it and *how much* of each goes to principal versus interest.

## The rule

**Oldest installment first, and within an installment: penalty, then interest, then principal.**

Concretely, for installment `n` (in ascending `installment_number` order):

1. Apply as much of the remaining payment as possible to `n`'s outstanding penalty (late fee), if any.
2. Apply whatever's left to `n`'s outstanding interest.
3. Apply whatever's left after that to `n`'s outstanding principal.
4. Move to installment `n+1` only once `n` is either fully paid or the payment amount is exhausted.

Penalty-first exists because of the scheduled overdue-check job (`docs/scheduler.md`): once an installment has a late fee attached, a customer catching up owes that fee before anything else on that installment counts as progress against interest or principal — the conventional incentive structure in consumer lending, and it means an overdue installment can never look "mostly paid" while its penalty sits unpaid underneath.

Interest-before-principal (once any penalty is cleared) is the conventional consumer-lending order beyond that, and it's a genuine choice with a real alternative (principal-first allocation, which reduces the balance interest accrues on faster and favors the borrower). Interest-first is what most lenders actually do, and it means a partial payment always fully clears the *nearer-term* obligation before touching anything further out — a predictable, easy-to-explain rule for a customer checking their statement.

## Worked example

Loan: 60,000 principal, 3,600 interest, 6 equal installments (10,000 principal + 600 interest = 10,600/month).

A repayment of **11,100**:

| Step | Installment | Interest applied | Principal applied | Remaining after |
|---|---|---|---|---|
| 1 | #1 | 600 (clears it) | 10,000 (clears it) | 500 |
| 2 | #2 | 500 (partial) | 0 | 0 |

Installment #1 is now `paid`. Installment #2 is `partially_paid` with 500 of its 600 interest covered — the next repayment against this loan will finish #2's interest before touching its principal.

## Why allocation is a separate, pure service

`RepaymentAllocationService::allocate()` takes a loan and an amount and returns a *plan* — it does not write anything to the database itself. `RepaymentService::create()` is responsible for applying that plan and persisting it, inside its own locked transaction. Splitting these apart means the allocation math (the part with actual business logic worth getting right and testing thoroughly) can be exercised without needing a database at all, and the transactional/locking concerns in `RepaymentService` stay focused on concurrency correctness rather than being tangled up with "which installment gets how much."

## Interaction with the outstanding-balance check

Before allocation ever runs, `RepaymentService::create()` checks the requested amount against `Loan::totalOutstanding()` (principal + interest across the whole loan, not just the next installment) and rejects anything larger with `InsufficientOutstandingBalanceException`. A customer is allowed to pay ahead — covering future installments in one payment — but never to pay more than the loan actually still owes.
