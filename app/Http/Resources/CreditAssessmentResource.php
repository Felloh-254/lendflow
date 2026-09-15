<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'credit_score' => $this->credit_score,
            'monthly_income' => (float) $this->monthly_income,
            'existing_debt' => (float) $this->existing_debt,
            'debt_to_income_ratio' => (float) $this->debt_to_income_ratio,
            'risk_level' => $this->risk_level,
            'recommendation' => $this->recommendation,
            'assessed_by' => $this->assessed_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
