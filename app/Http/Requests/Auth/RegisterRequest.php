<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint — anyone can attempt to register as a customer.
        // Staff accounts (loan_officer/manager/admin) are provisioned by
        // an admin via a separate endpoint, never through public signup.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
            'phone' => ['required', 'string', 'max:20', 'unique:customers,phone'],
            'national_id' => ['required', 'string', 'max:50', 'unique:customers,national_id'],
            'date_of_birth' => ['required', 'date', 'before:-18 years'],
            'monthly_income' => ['required', 'numeric', 'min:0'],
            'employment_status' => ['required', 'string', 'max:100'],
        ];
    }
}
