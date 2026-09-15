<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $principal = fake()->numberBetween(10000, 100000);
        $interest = round($principal * 0.15, 2);

        return [
            'customer_id' => Customer::factory(),
            'loan_application_id' => LoanApplication::factory()->state(['status' => LoanApplication::STATUS_APPROVED]),
            'loan_product_id' => LoanProduct::factory(),
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'total_amount' => $principal + $interest,
            'outstanding_principal' => $principal,
            'outstanding_interest' => $interest,
            'status' => Loan::STATUS_APPROVED,
            'approved_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Loan::STATUS_ACTIVE,
            'disbursed_at' => now(),
        ]);
    }
}
