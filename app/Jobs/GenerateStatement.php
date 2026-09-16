<?php

namespace App\Jobs;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * A statement (every transaction, every installment, final totals) is
 * exactly the kind of work that belongs on a queue rather than in the
 * request cycle: it's read-heavy, has no time pressure (nobody's
 * waiting on it to render the "repayment accepted" response), and — if
 * this were extended to render a real PDF — could take long enough to
 * make a synchronous HTTP request an obviously bad place for it.
 */
class GenerateStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Loan $loan) {}

    public function handle(): void
    {
        $transactionCount = $this->loan->transactions()->count();

        Log::info('[statement] Generating final loan statement', [
            'loan_id' => $this->loan->id,
            'customer_id' => $this->loan->customer_id,
            'total_amount' => (float) $this->loan->total_amount,
            'transaction_count' => $transactionCount,
            'completed_at' => optional($this->loan->completed_at)->toIso8601String(),
        ]);
    }
}
