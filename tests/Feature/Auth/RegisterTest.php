<?php

use App\Models\User;

it('registers a new customer and returns access + refresh tokens', function () {
    $payload = [
        'name' => 'Jane Wanjiku',
        'email' => 'jane@example.com',
        'password' => 'StrongPass1',
        'password_confirmation' => 'StrongPass1',
        'phone' => '254712345678',
        'national_id' => '12345678',
        'date_of_birth' => '1995-01-01',
        'monthly_income' => 80000,
        'employment_status' => 'employed',
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertCreated()
        ->assertJsonStructure(['user' => ['id', 'email', 'role'], 'access_token', 'refresh_token', 'expires_in']);

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();

    $user = User::where('email', 'jane@example.com')->first();
    expect($user->role)->toBe(User::ROLE_CUSTOMER);
    expect($user->customer)->not->toBeNull();
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Someone',
        'email' => 'taken@example.com',
        'password' => 'StrongPass1',
        'password_confirmation' => 'StrongPass1',
        'phone' => '254700000000',
        'national_id' => '99999999',
        'date_of_birth' => '1990-01-01',
        'monthly_income' => 50000,
        'employment_status' => 'employed',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects registration for an applicant under 18', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Too Young',
        'email' => 'young@example.com',
        'password' => 'StrongPass1',
        'password_confirmation' => 'StrongPass1',
        'phone' => '254700000001',
        'national_id' => '88888888',
        'date_of_birth' => now()->subYears(10)->toDateString(),
        'monthly_income' => 10000,
        'employment_status' => 'unemployed',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('date_of_birth');
});
