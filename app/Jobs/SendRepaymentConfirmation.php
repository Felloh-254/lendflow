<?php

namespace App\Jobs;

use App\Models\Repayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendRepaymentConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Repayment $repayment) {}

    public function handle(): void
    {
        Log::info('[notification] Repayment received', [
            'repayment_id' => $this->repayment->id,
            'loan_id' => $this->repayment->loan_id,
            'amount' => (float) $this->repayment->amount,
            'payment_method' => $this->repayment->payment_method,
            'channel' => 'email/sms (simulated)',
        ]);
    }
}
