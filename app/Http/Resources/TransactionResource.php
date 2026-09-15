<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_id' => $this->loan_id,
            'reference' => $this->reference,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'external_reference' => $this->external_reference,
            'ledger_entries' => LedgerEntryResource::collection($this->whenLoaded('ledgerEntries')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
