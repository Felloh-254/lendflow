<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'loan_application_id' => $this->loan_application_id,
            'loan_product' => new LoanProductResource($this->whenLoaded('loanProduct')),
            'principal_amount' => (float) $this->principal_amount,
            'interest_amount' => (float) $this->interest_amount,
            'total_amount' => (float) $this->total_amount,
            'outstanding_principal' => (float) $this->outstanding_principal,
            'outstanding_interest' => (float) $this->outstanding_interest,
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'disbursed_at' => $this->disbursed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
