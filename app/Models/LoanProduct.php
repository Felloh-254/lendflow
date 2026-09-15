<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'description',
        'min_amount',
        'max_amount',
        'interest_rate',
        'term_min',
        'term_max',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
        ];
    }

    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function isAmountWithinRange(float $amount): bool
    {
        return $amount >= (float) $this->min_amount && $amount <= (float) $this->max_amount;
    }

    public function isTermWithinRange(int $months): bool
    {
        return $months >= $this->term_min && $months <= $this->term_max;
    }
}
