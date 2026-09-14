<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'phone' => $this->phone,
            'national_id' => $this->national_id,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'monthly_income' => (float) $this->monthly_income,
            'employment_status' => $this->employment_status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
