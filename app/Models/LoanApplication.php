<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The only legal status transitions. Every write to `status` in this
     * application goes through LoanApplicationService, which checks this
     * map before mutating anything — see canTransitionTo() below.
     *
     * This is intentionally a plain array, not a state-machine package:
     * six states with this few transitions doesn't need one, and a plain
     * array is something anyone reading the code can verify at a glance
     * against docs/loan-lifecycle.md.
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_SUBMITTED => [self::STATUS_UNDER_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_UNDER_REVIEW => [self::STATUS_APPROVED, self::STATUS_REJECTED],
        self::STATUS_APPROVED => [],
        self::STATUS_REJECTED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'customer_id',
        'loan_product_id',
        'amount_requested',
        'term_months',
        'purpose',
        'status',
        'assigned_loan_officer_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function creditAssessment()
    {
        return $this->hasOne(CreditAssessment::class);
    }

    public function assignedLoanOfficer()
    {
        return $this->belongsTo(User::class, 'assigned_loan_officer_id');
    }

    public function loan()
    {
        return $this->hasOne(Loan::class);
    }

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
