<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'min_amount' => (float) $this->min_amount,
            'max_amount' => (float) $this->max_amount,
            'interest_rate' => (float) $this->interest_rate,
            'term_min' => $this->term_min,
            'term_max' => $this->term_max,
            'status' => $this->status,
        ];
    }
}
