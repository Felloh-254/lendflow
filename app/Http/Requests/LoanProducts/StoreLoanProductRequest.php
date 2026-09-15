<?php

namespace App\Http\Requests\LoanProducts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gate check happens in the controller.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'min_amount' => ['required', 'numeric', 'min:1'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'term_min' => ['required', 'integer', 'min:1'],
            'term_max' => ['required', 'integer', 'gte:term_min'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
