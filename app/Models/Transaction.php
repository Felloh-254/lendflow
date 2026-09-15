<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Financial transactions are immutable once written: `const UPDATED_AT =
 * null` means Eloquent never touches this row after creation, and no
 * service in the app calls `Transaction::update()` — corrections happen
 * by posting a new `reversal` transaction that references what it's
 * undoing, never by mutating history. See docs/transactions.md.
 */
class Transaction extends Model
{
    const UPDATED_AT = null;

    public const TYPE_DISBURSEMENT = 'disbursement';

    public const TYPE_REPAYMENT = 'repayment';

    public const TYPE_FEE = 'fee';

    public const TYPE_REVERSAL = 'reversal';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'loan_id',
        'reference',
        'type',
        'amount',
        'status',
        'external_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
