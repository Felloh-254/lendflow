<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepaymentSchedule extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'loan_id',
        'installment_number',
        'due_date',
        'principal_due',
        'interest_due',
        'penalty_due',
        'total_due',
        'principal_paid',
        'interest_paid',
        'penalty_paid',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_due' => 'decimal:2',
            'interest_due' => 'decimal:2',
            'penalty_due' => 'decimal:2',
            'total_due' => 'decimal:2',
            'principal_paid' => 'decimal:2',
            'interest_paid' => 'decimal:2',
            'penalty_paid' => 'decimal:2',
        ];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function remainingPrincipal(): float
    {
        return round((float) $this->principal_due - (float) $this->principal_paid, 2);
    }

    public function remainingInterest(): float
    {
        return round((float) $this->interest_due - (float) $this->interest_paid, 2);
    }

    public function remainingPenalty(): float
    {
        return round((float) $this->penalty_due - (float) $this->penalty_paid, 2);
    }

    public function remainingTotal(): float
    {
        return round($this->remainingPrincipal() + $this->remainingInterest() + $this->remainingPenalty(), 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->remainingTotal() <= 0.005;
    }
}
