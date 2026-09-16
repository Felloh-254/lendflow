<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'national_id',
        'date_of_birth',
        'monthly_income',
        'employment_status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'monthly_income' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function repayments()
    {
        return $this->hasMany(Repayment::class);
    }
}
