<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy check happens in the controller (`$this->authorize`), so
        // this stays true — it just means "you're allowed to submit this
        // request shape", not "you're allowed to update this record".
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->user()->customer?->id;

        return [
            'phone' => ['sometimes', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($customerId)],
            'monthly_income' => ['sometimes', 'numeric', 'min:0'],
            'employment_status' => ['sometimes', 'string', 'max:100'],
            // national_id and date_of_birth are intentionally NOT editable
            // via self-service — they're identity-verification fields;
            // changing them would need a re-verification flow, out of
            // scope for this MVP.
        ];
    }
}
