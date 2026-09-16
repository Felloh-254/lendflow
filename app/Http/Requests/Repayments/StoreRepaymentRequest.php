<?php

namespace App\Http\Requests\Repayments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy check (LoanPolicy::repay) happens in the controller.
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['mpesa', 'bank', 'wallet'])],
            'external_reference' => ['nullable', 'string', 'max:255', 'unique:repayments,external_reference'],
        ];
    }
}
