<?php

namespace App\Events;

use App\Models\Repayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RepaymentReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Repayment $repayment) {}
}
