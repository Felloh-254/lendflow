<?php

namespace App\Listeners;

use App\Events\LoanApproved;
use App\Jobs\SendLoanApprovalNotification;

/**
 * Listeners in this app are deliberately thin and synchronous — their
 * only job is to decide WHAT queued work an event should trigger and
 * dispatch it. The actual work (and its retry/failure handling) lives in
 * the Job class, not here. Keeping that split means a listener never
 * itself does slow I/O — dispatching a job is a fast, local
 * Redis push — so registering more listeners on an event never risks
 * slowing down the request that triggered it.
 */
class SendLoanApprovalNotificationListener
{
    public function handle(LoanApproved $event): void
    {
        SendLoanApprovalNotification::dispatch($event->loanApplication);
    }
}
