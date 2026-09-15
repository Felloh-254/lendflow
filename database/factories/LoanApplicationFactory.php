<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanApplicationFactory extends Factory
{
    protected $model = LoanApplication::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'loan_product_id' => LoanProduct::factory(),
            'amount_requested' => fake()->numberBetween(10000, 100000),
            'term_months' => fake()->numberBetween(3, 12),
            'purpose' => fake()->sentence(),
            'status' => LoanApplication::STATUS_DRAFT,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => LoanApplication::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn () => [
            'status' => LoanApplication::STATUS_UNDER_REVIEW,
            'submitted_at' => now(),
        ]);
    }
}
