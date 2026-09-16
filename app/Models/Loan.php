<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PENDING_DISBURSEMENT = 'pending_disbursement';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DEFAULTED = 'defaulted';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * `pending_disbursement` exists in the schema for institutions where
     * disbursement is a separate, deliberate step (e.g. waiting on an
     * external bank transfer to clear) — but LendFlow's mock disbursement
     * is synchronous, so `disburse()` currently moves `approved` straight
     * to `active`. The state and the transition path are both here so
     * that behavior is a real extension point, not a schema change.
     */
    public const TRANSITIONS = [
        self::STATUS_APPROVED => [self::STATUS_PENDING_DISBURSEMENT, self::STATUS_ACTIVE, self::STATUS_CANCELLED],
        self::STATUS_PENDING_DISBURSEMENT => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
        self::STATUS_ACTIVE => [self::STATUS_OVERDUE, self::STATUS_COMPLETED, self::STATUS_DEFAULTED],
        self::STATUS_OVERDUE => [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_DEFAULTED],
        self::STATUS_COMPLETED => [],
        self::STATUS_DEFAULTED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'customer_id',
        'loan_application_id',
        'loan_product_id',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'outstanding_principal',
        'outstanding_interest',
        'outstanding_fees',
        'status',
        'approved_at',
        'disbursed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'outstanding_principal' => 'decimal:2',
            'outstanding_interest' => 'decimal:2',
            'outstanding_fees' => 'decimal:2',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function repaymentSchedules()
    {
        return $this->hasMany(RepaymentSchedule::class)->orderBy('installment_number');
    }

    public function repayments()
    {
        return $this->hasMany(Repayment::class);
    }

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function totalOutstanding(): float
    {
        return (float) $this->outstanding_principal + (float) $this->outstanding_interest + (float) $this->outstanding_fees;
    }
}
