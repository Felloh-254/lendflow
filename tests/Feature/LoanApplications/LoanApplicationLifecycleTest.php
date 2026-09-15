<?php

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;

function makeCustomerWithProfile(array $customerAttrs = []): array
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id, ...$customerAttrs]);

    return [$user, $customer];
}

it('lets a customer create a draft application within product limits', function () {
    [$user, $customer] = makeCustomerWithProfile();
    $product = LoanProduct::factory()->create(['min_amount' => 10000, 'max_amount' => 100000, 'term_min' => 3, 'term_max' => 12]);

    $response = $this->withHeaders($this->apiHeaders($user))
        ->postJson('/api/v1/loan-applications', [
            'loan_product_id' => $product->id,
            'amount_requested' => 50000,
            'term_months' => 6,
            'purpose' => 'School fees',
        ]);

    $response->assertCreated()->assertJsonPath('status', LoanApplication::STATUS_DRAFT);
});

it('rejects a requested amount outside the product range', function () {
    [$user, $customer] = makeCustomerWithProfile();
    $product = LoanProduct::factory()->create(['min_amount' => 10000, 'max_amount' => 100000]);

    $this->withHeaders($this->apiHeaders($user))
        ->postJson('/api/v1/loan-applications', [
            'loan_product_id' => $product->id,
            'amount_requested' => 500000,
            'term_months' => 6,
            'purpose' => 'Too much',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount_requested');
});

it('walks an application through submit -> assess -> approve', function () {
    [$customerUser, $customer] = makeCustomerWithProfile(['monthly_income' => 80000]);
    $officer = User::factory()->loanOfficer()->create();
    $manager = User::factory()->manager()->create();

    $application = LoanApplication::factory()->create([
        'customer_id' => $customer->id,
        'amount_requested' => 30000,
    ]);

    // Customer submits
    $this->withHeaders($this->apiHeaders($customerUser))
        ->postJson("/api/v1/loan-applications/{$application->id}/submit")
        ->assertOk()
        ->assertJsonPath('status', LoanApplication::STATUS_SUBMITTED);

    // Loan officer assesses -> moves to under_review and self-assigns
    $assessResponse = $this->withHeaders($this->apiHeaders($officer))
        ->postJson("/api/v1/loan-applications/{$application->id}/assess")
        ->assertOk();

    $assessResponse->assertJsonPath('status', LoanApplication::STATUS_UNDER_REVIEW);
    $assessResponse->assertJsonPath('assigned_loan_officer_id', $officer->id);
    expect($assessResponse->json('credit_assessment.recommendation'))->not->toBeNull();

    // Manager approves
    $this->withHeaders($this->apiHeaders($manager))
        ->postJson("/api/v1/loan-applications/{$application->id}/approve")
        ->assertOk()
        ->assertJsonPath('status', LoanApplication::STATUS_APPROVED);
});

it('prevents approving an application that has not been submitted', function () {
    [, $customer] = makeCustomerWithProfile();
    $manager = User::factory()->manager()->create();
    $application = LoanApplication::factory()->create(['customer_id' => $customer->id]); // draft

    $this->withHeaders($this->apiHeaders($manager))
        ->postJson("/api/v1/loan-applications/{$application->id}/approve")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_state_transition');
});

it('prevents approving an already-approved application', function () {
    [, $customer] = makeCustomerWithProfile();
    $manager = User::factory()->manager()->create();
    $application = LoanApplication::factory()->create([
        'customer_id' => $customer->id,
        'status' => LoanApplication::STATUS_APPROVED,
    ]);

    $this->withHeaders($this->apiHeaders($manager))
        ->postJson("/api/v1/loan-applications/{$application->id}/approve")
        ->assertStatus(409);
});

it('rejects an application with an optional reason', function () {
    [, $customer] = makeCustomerWithProfile();
    $manager = User::factory()->manager()->create();
    $application = LoanApplication::factory()->underReview()->create(['customer_id' => $customer->id]);

    $this->withHeaders($this->apiHeaders($manager))
        ->postJson("/api/v1/loan-applications/{$application->id}/reject", ['reason' => 'Insufficient income'])
        ->assertOk()
        ->assertJsonPath('status', LoanApplication::STATUS_REJECTED);
});

it('lets a customer cancel their own draft application', function () {
    [$user, $customer] = makeCustomerWithProfile();
    $application = LoanApplication::factory()->create(['customer_id' => $customer->id]);

    $this->withHeaders($this->apiHeaders($user))
        ->postJson("/api/v1/loan-applications/{$application->id}/cancel")
        ->assertOk()
        ->assertJsonPath('status', LoanApplication::STATUS_CANCELLED);
});

it('prevents cancelling an approved application', function () {
    [$user, $customer] = makeCustomerWithProfile();
    $application = LoanApplication::factory()->create([
        'customer_id' => $customer->id,
        'status' => LoanApplication::STATUS_APPROVED,
    ]);

    $this->withHeaders($this->apiHeaders($user))
        ->postJson("/api/v1/loan-applications/{$application->id}/cancel")
        ->assertStatus(409);
});
