<?php

namespace Database\Factories;

use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanProductFactory extends Factory
{
    protected $model = LoanProduct::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Personal Loan', 'Business Loan', 'Emergency Loan']).' '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
            'min_amount' => 5000,
            'max_amount' => 500000,
            'interest_rate' => fake()->randomFloat(2, 8, 24),
            'term_min' => 1,
            'term_max' => 24,
            'status' => LoanProduct::STATUS_ACTIVE,
        ];
    }
}
