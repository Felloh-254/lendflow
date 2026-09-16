<?php

namespace App\Listeners;

use App\Events\RepaymentReceived;
use App\Jobs\SendRepaymentConfirmation;

class SendRepaymentConfirmationListener
{
    public function handle(RepaymentReceived $event): void
    {
        SendRepaymentConfirmation::dispatch($event->repayment);
    }
}
