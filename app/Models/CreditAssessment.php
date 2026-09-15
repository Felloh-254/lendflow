<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditAssessment extends Model
{
    const UPDATED_AT = null;

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const RECOMMEND_APPROVE = 'approve';

    public const RECOMMEND_REJECT = 'reject';

    protected $fillable = [
        'loan_application_id',
        'credit_score',
        'monthly_income',
        'existing_debt',
        'debt_to_income_ratio',
        'risk_level',
        'recommendation',
        'assessed_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_income' => 'decimal:2',
            'existing_debt' => 'decimal:2',
            'debt_to_income_ratio' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function assessedBy()
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
