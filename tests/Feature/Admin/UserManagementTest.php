<?php

use App\Models\User;

it('allows an admin to list users', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();

    $this->withHeaders($this->apiHeaders($admin))
        ->getJson('/api/v1/admin/users')
        ->assertOk()
        ->assertJsonCount(4, 'data'); // 3 + the admin itself
});

it('forbids a customer from listing users', function () {
    $customer = User::factory()->create();

    $this->withHeaders($this->apiHeaders($customer))
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();
});

it('forbids a loan officer from listing users', function () {
    $officer = User::factory()->loanOfficer()->create();

    $this->withHeaders($this->apiHeaders($officer))
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();
});

it('allows an admin to change another user\'s role and status', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->withHeaders($this->apiHeaders($admin))
        ->patchJson("/api/v1/admin/users/{$target->id}", [
            'role' => User::ROLE_LOAN_OFFICER,
            'status' => User::STATUS_SUSPENDED,
        ])
        ->assertOk()
        ->assertJsonPath('role', User::ROLE_LOAN_OFFICER)
        ->assertJsonPath('status', User::STATUS_SUSPENDED);
});

it('forbids an admin from modifying their own account through this endpoint', function () {
    $admin = User::factory()->admin()->create();

    $this->withHeaders($this->apiHeaders($admin))
        ->patchJson("/api/v1/admin/users/{$admin->id}", ['status' => User::STATUS_SUSPENDED])
        ->assertForbidden();
});

it('forbids a manager from changing user roles', function () {
    $manager = User::factory()->manager()->create();
    $target = User::factory()->create();

    $this->withHeaders($this->apiHeaders($manager))
        ->patchJson("/api/v1/admin/users/{$target->id}", ['role' => User::ROLE_ADMIN])
        ->assertForbidden();
});
