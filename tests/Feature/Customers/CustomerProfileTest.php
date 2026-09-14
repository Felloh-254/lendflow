<?php

use App\Models\Customer;
use App\Models\User;

it("lets a customer view their own profile", function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $this->withHeaders($this->apiHeaders($user))
        ->getJson('/api/v1/customers/me')
        ->assertOk()
        ->assertJsonPath('id', $customer->id);
});

it("lets a customer update editable fields on their own profile", function () {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id, 'monthly_income' => 50000]);

    $this->withHeaders($this->apiHeaders($user))
        ->patchJson('/api/v1/customers/me', ['monthly_income' => 90000])
        ->assertOk()
        ->assertJsonPath('monthly_income', 90000);
});

it("does not allow a customer to edit their national_id via self-service", function () {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id, 'national_id' => '11111111']);

    $this->withHeaders($this->apiHeaders($user))
        ->patchJson('/api/v1/customers/me', ['national_id' => '99999999'])
        ->assertOk();

    expect($user->customer->fresh()->national_id)->toBe('11111111');
});

it("lets a loan officer view (but the endpoint is scoped to /me, so this is a control) — staff cannot use /customers/me for someone else's profile", function () {
    $officer = User::factory()->loanOfficer()->create();

    // Loan officers have no `customer` record of their own, so hitting
    // /me should 404 rather than leaking another customer's data.
    $this->withHeaders($this->apiHeaders($officer))
        ->getJson('/api/v1/customers/me')
        ->assertNotFound();
});
