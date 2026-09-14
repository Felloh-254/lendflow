<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone' => fake()->unique()->numerify('2547########'),
            'national_id' => fake()->unique()->numerify('########'),
            'date_of_birth' => fake()->date('Y-m-d', '-18 years'),
            'monthly_income' => fake()->numberBetween(20000, 300000),
            'employment_status' => fake()->randomElement(['employed', 'self_employed', 'unemployed']),
        ];
    }
}
