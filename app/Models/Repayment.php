<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Repayment extends Model
{
    public const METHOD_MPESA = 'mpesa';

    public const METHOD_BANK = 'bank';

    public const METHOD_WALLET = 'wallet';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'loan_id',
        'customer_id',
        'transaction_id',
        'amount',
        'payment_method',
        'external_reference',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
