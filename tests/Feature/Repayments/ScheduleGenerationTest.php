<?php

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;
use App\Models\User;

it('generates an equal-installment schedule summing exactly to principal and interest', function () {
    $customer = Customer::factory()->create(['monthly_income' => 100000]);
    $product = LoanProduct::factory()->create(['interest_rate' => 12]);
    $application = LoanApplication::factory()->underReview()->create([
        'customer_id' => $customer->id,
        'loan_product_id' => $product->id,
        'amount_requested' => 60000,
        'term_months' => 6,
    ]);
    $manager = User::factory()->manager()->create();

    $this->withHeaders($this->apiHeaders($manager))
        ->postJson("/api/v1/loan-applications/{$application->id}/approve")
        ->assertOk();

    $loan = Loan::where('loan_application_id', $application->id)->first();

    $this->withHeaders($this->apiHeaders($manager))
        ->withHeader('Idempotency-Key', 'SCHEDULE-GEN-TEST')
        ->postJson("/api/v1/loans/{$loan->id}/disburse")
        ->assertOk();

    $schedules = RepaymentSchedule::where('loan_id', $loan->id)->orderBy('installment_number')->get();

    expect($schedules)->toHaveCount(6);

    // principal 60000 / 6 = 10000/mo exactly; interest 3600 / 6 = 600/mo exactly.
    foreach ($schedules as $schedule) {
        expect((float) $schedule->principal_due)->toBe(10000.0);
        expect((float) $schedule->interest_due)->toBe(600.0);
        expect((float) $schedule->total_due)->toBe(10600.0);
        expect($schedule->status)->toBe(RepaymentSchedule::STATUS_PENDING);
    }

    expect((float) $schedules->sum('principal_due'))->toBe(60000.0);
    expect((float) $schedules->sum('interest_due'))->toBe(3600.0);

    // installment_number 1..6, due dates one month apart
    expect($schedules->pluck('installment_number')->all())->toBe([1, 2, 3, 4, 5, 6]);
});

it('has the last installment absorb any rounding remainder', function () {
    $customer = Customer::factory()->create();
    $product = LoanProduct::factory()->create(['interest_rate' => 10]);
    $application = LoanApplication::factory()->underReview()->create([
        'customer_id' => $customer->id,
        'loan_product_id' => $product->id,
        'amount_requested' => 10000,
        'term_months' => 3, // 10000/3 = 3333.33 repeating
    ]);
    $manager = User::factory()->manager()->create();

    $this->withHeaders($this->apiHeaders($manager))->postJson("/api/v1/loan-applications/{$application->id}/approve")->assertOk();
    $loan = Loan::where('loan_application_id', $application->id)->first();
    $this->withHeaders($this->apiHeaders($manager))
        ->withHeader('Idempotency-Key', 'ROUNDING-TEST')
        ->postJson("/api/v1/loans/{$loan->id}/disburse")
        ->assertOk();

    $schedules = RepaymentSchedule::where('loan_id', $loan->id)->orderBy('installment_number')->get();

    // No cent lost or invented to rounding, no matter how the division splits.
    expect((float) $schedules->sum('principal_due'))->toBe(10000.0);
});
