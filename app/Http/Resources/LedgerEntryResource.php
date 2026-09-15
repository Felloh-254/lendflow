<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'account_code' => $this->account_code,
            'entry_type' => $this->entry_type,
            'amount' => (float) $this->amount,
        ];
    }
}
