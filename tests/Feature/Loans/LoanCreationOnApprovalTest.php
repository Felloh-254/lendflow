<?php

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;

it('creates a Loan record when a loan application is approved', function () {
    $customer = Customer::factory()->create(['monthly_income' => 80000]);
    $product = LoanProduct::factory()->create(['interest_rate' => 12]);
    $application = LoanApplication::factory()->underReview()->create([
        'customer_id' => $customer->id,
        'loan_product_id' => $product->id,
        'amount_requested' => 60000,
        'term_months' => 6,
    ]);
    $manager = User::factory()->manager()->create();

    expect(Loan::where('loan_application_id', $application->id)->exists())->toBeFalse();

    $this->withHeaders($this->apiHeaders($manager))
        ->postJson("/api/v1/loan-applications/{$application->id}/approve")
        ->assertOk();

    $loan = Loan::where('loan_application_id', $application->id)->first();

    expect($loan)->not->toBeNull();
    expect($loan->status)->toBe(Loan::STATUS_APPROVED);
    expect((float) $loan->principal_amount)->toBe(60000.0);
    // 60000 * 12% * (6/12) = 3600 interest
    expect((float) $loan->interest_amount)->toBe(3600.0);
    expect((float) $loan->outstanding_principal)->toBe(60000.0);
});
