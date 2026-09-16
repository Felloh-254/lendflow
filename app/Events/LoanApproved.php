<?php

namespace App\Events;

use App\Models\LoanApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched from inside LoanApplicationService::approve()'s
 * DB::transaction() — see config/queue.php's `after_commit` note for why
 * that's safe: any queued work a listener triggers waits for this
 * transaction to actually commit before running.
 */
class LoanApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly LoanApplication $loanApplication) {}
}
