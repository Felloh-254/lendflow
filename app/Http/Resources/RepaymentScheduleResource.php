<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepaymentScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date?->toDateString(),
            'principal_due' => (float) $this->principal_due,
            'interest_due' => (float) $this->interest_due,
            'penalty_due' => (float) $this->penalty_due,
            'total_due' => (float) $this->total_due,
            'principal_paid' => (float) $this->principal_paid,
            'interest_paid' => (float) $this->interest_paid,
            'penalty_paid' => (float) $this->penalty_paid,
            'status' => $this->status,
        ];
    }
}
