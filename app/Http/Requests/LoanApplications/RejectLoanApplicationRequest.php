<?php

namespace App\Http\Requests\LoanApplications;

use Illuminate\Foundation\Http\FormRequest;

class RejectLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy check happens in the controller.
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
