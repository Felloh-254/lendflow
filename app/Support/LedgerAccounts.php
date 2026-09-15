<?php

namespace App\Support;

/**
 * LendFlow doesn't model a full chart of accounts — that's out of scope
 * for an MVP ledger. These are the fixed account codes LedgerService
 * posts entries against. Keeping them as named constants (rather than
 * inline strings scattered across services) means a typo in an account
 * code is a compile-time-visible mistake, not a silent bookkeeping bug.
 */
final class LedgerAccounts
{
    /** The institution's cash/bank position. */
    public const CASH = 'cash';

    /** Principal the institution is owed by borrowers, in aggregate. */
    public const LOAN_PRINCIPAL_RECEIVABLE = 'loan_principal_receivable';

    /** Interest the institution is owed by borrowers, in aggregate. */
    public const INTEREST_RECEIVABLE = 'interest_receivable';

    /** Interest earned/recognized. */
    public const INTEREST_INCOME = 'interest_income';
}
