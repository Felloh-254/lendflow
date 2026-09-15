<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'loan_product' => new LoanProductResource($this->whenLoaded('loanProduct')),
            'amount_requested' => (float) $this->amount_requested,
            'term_months' => $this->term_months,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'assigned_loan_officer_id' => $this->assigned_loan_officer_id,
            'credit_assessment' => new CreditAssessmentResource($this->whenLoaded('creditAssessment')),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
