<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in with valid credentials', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('CorrectPass1'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'CorrectPass1',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user', 'access_token', 'refresh_token', 'expires_in']);
});

it('rejects an incorrect password without revealing whether the email exists', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('CorrectPass1'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'WrongPassword',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_credentials');
});

it('rejects login for a suspended account', function () {
    User::factory()->suspended()->create([
        'email' => 'suspended@example.com',
        'password' => Hash::make('CorrectPass1'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'suspended@example.com',
        'password' => 'CorrectPass1',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error.code', 'account_inactive');
});
