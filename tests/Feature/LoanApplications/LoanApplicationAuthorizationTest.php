<?php

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\User;

it("scopes a customer's index to only their own applications", function () {
    $userA = User::factory()->create();
    $customerA = Customer::factory()->create(['user_id' => $userA->id]);
    $userB = User::factory()->create();
    $customerB = Customer::factory()->create(['user_id' => $userB->id]);

    LoanApplication::factory()->create(['customer_id' => $customerA->id]);
    LoanApplication::factory()->create(['customer_id' => $customerB->id]);

    $response = $this->withHeaders($this->apiHeaders($userA))
        ->getJson('/api/v1/loan-applications')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.customer_id'))->toBe($customerA->id);
});

it('forbids a customer from viewing another customer\'s application', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $customerB = Customer::factory()->create(['user_id' => $userB->id]);
    $application = LoanApplication::factory()->create(['customer_id' => $customerB->id]);

    $this->withHeaders($this->apiHeaders($userA))
        ->getJson("/api/v1/loan-applications/{$application->id}")
        ->assertForbidden();
});

it('lets a second loan officer assess an unassigned application', function () {
    $officer = User::factory()->loanOfficer()->create();
    $customer = Customer::factory()->create();
    $application = LoanApplication::factory()->submitted()->create(['customer_id' => $customer->id]);

    $this->withHeaders($this->apiHeaders($officer))
        ->postJson("/api/v1/loan-applications/{$application->id}/assess")
        ->assertOk();
});

it('blocks a loan officer from assessing an application already assigned to a colleague', function () {
    $officerA = User::factory()->loanOfficer()->create();
    $officerB = User::factory()->loanOfficer()->create();
    $customer = Customer::factory()->create();
    $application = LoanApplication::factory()->submitted()->create(['customer_id' => $customer->id]);

    $this->withHeaders($this->apiHeaders($officerA))
        ->postJson("/api/v1/loan-applications/{$application->id}/assess")
        ->assertOk();

    $this->withHeaders($this->apiHeaders($officerB))
        ->postJson("/api/v1/loan-applications/{$application->id}/assess")
        ->assertForbidden();
});

it('allows the same officer to re-assess (re-run scoring on) their own assigned application', function () {
    $officer = User::factory()->loanOfficer()->create();
    $customer = Customer::factory()->create();
    $application = LoanApplication::factory()->submitted()->create(['customer_id' => $customer->id]);

    $this->withHeaders($this->apiHeaders($officer))
        ->postJson("/api/v1/loan-applications/{$application->id}/assess")
        ->assertOk();

    $this->withHeaders($this->apiHeaders($officer))
        ->postJson("/api/v1/loan-applications/{$application->id}/assess")
        ->assertOk();
});

it('forbids a customer from approving their own application', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $application = LoanApplication::factory()->underReview()->create(['customer_id' => $customer->id]);

    $this->withHeaders($this->apiHeaders($user))
        ->postJson("/api/v1/loan-applications/{$application->id}/approve")
        ->assertForbidden();
});

it('forbids a loan officer from approving an application (recommend only, not decide)', function () {
    $officer = User::factory()->loanOfficer()->create();
    $customer = Customer::factory()->create();
    $application = LoanApplication::factory()->underReview()->create([
        'customer_id' => $customer->id,
        'assigned_loan_officer_id' => $officer->id,
    ]);

    $this->withHeaders($this->apiHeaders($officer))
        ->postJson("/api/v1/loan-applications/{$application->id}/approve")
        ->assertForbidden();
});
