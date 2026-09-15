<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    const UPDATED_AT = null;

    public const DEBIT = 'debit';

    public const CREDIT = 'credit';

    protected $fillable = [
        'transaction_id',
        'account_code',
        'entry_type',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
