<?php

namespace App\Http\Requests\LoanApplications;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\LoanApplication::class);
    }

    public function rules(): array
    {
        return [
            'loan_product_id' => ['required', 'integer', 'exists:loan_products,id'],
            'amount_requested' => ['required', 'numeric', 'min:1'],
            'term_months' => ['required', 'integer', 'min:1'],
            'purpose' => ['required', 'string', 'max:500'],
        ];
    }
}
