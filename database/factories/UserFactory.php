<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('Password123'),
            'role' => User::ROLE_CUSTOMER,
            'status' => User::STATUS_ACTIVE,
        ];
    }

    public function loanOfficer(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_LOAN_OFFICER]);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_MANAGER]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => User::STATUS_SUSPENDED]);
    }
}
