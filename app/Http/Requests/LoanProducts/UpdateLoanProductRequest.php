<?php

namespace App\Http\Requests\LoanProducts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate check happens in the controller.
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'min_amount' => ['sometimes', 'numeric', 'min:1'],
            'max_amount' => ['sometimes', 'numeric', 'gte:min_amount'],
            'interest_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'term_min' => ['sometimes', 'integer', 'min:1'],
            'term_max' => ['sometimes', 'integer', 'gte:term_min'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
