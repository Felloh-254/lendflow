<?php

namespace App\Jobs;

use App\Models\LoanApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued rather than run synchronously in the request/response cycle
 * because sending a notification is an external I/O call (email/SMS
 * provider) with its own latency and failure modes that have nothing to
 * do with whether the approval itself succeeded — a slow or briefly-down
 * notification provider should never make a manager's "approve" request
 * hang or fail. See docs/queues.md.
 *
 * LendFlow doesn't integrate a real email/SMS provider (out of scope for
 * this MVP) — this logs what would be sent. Swapping in a real
 * Mail::to()->send(...) call is the natural extension point; the queueing
 * behavior and retry semantics don't need to change.
 */
class SendLoanApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly LoanApplication $loanApplication) {}

    public function handle(): void
    {
        $customer = $this->loanApplication->customer;

        Log::info('[notification] Loan application approved', [
            'loan_application_id' => $this->loanApplication->id,
            'customer_id' => $customer->id,
            'amount_requested' => (float) $this->loanApplication->amount_requested,
            'channel' => 'email/sms (simulated)',
        ]);
    }
}
