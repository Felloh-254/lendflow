<?php

namespace App\Events;

use App\Models\Loan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanDisbursed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Loan $loan) {}
}
