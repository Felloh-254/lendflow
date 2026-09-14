<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::in([
                User::ROLE_CUSTOMER,
                User::ROLE_LOAN_OFFICER,
                User::ROLE_MANAGER,
                User::ROLE_ADMIN,
            ])],
            'status' => ['sometimes', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED])],
        ];
    }
}
