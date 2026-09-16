<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_id' => $this->loan_id,
            'transaction_id' => $this->transaction_id,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'external_reference' => $this->external_reference,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
