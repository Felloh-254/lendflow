<?php

use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function loginAndGetTokens(string $email, string $password): array
{
    $response = test()->postJson('/api/v1/auth/login', compact('email', 'password'));

    return $response->json();
}

it('blocks access to a protected endpoint without a token', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'token_missing');
});

it('allows access to a protected endpoint with a valid access token', function () {
    $user = User::factory()->create(['password' => bcrypt('CorrectPass1')]);
    $tokens = loginAndGetTokens($user->email, 'CorrectPass1');

    $this->withHeader('Authorization', 'Bearer '.$tokens['access_token'])
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('rejects an expired or invalid access token', function () {
    $this->withHeader('Authorization', 'Bearer not-a-real-token')
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'token_invalid');
});

it('rotates the refresh token and invalidates the old one on reuse', function () {
    $user = User::factory()->create(['password' => bcrypt('CorrectPass1')]);
    $tokens = loginAndGetTokens($user->email, 'CorrectPass1');

    $first = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);
    $first->assertOk();
    $newRefreshToken = $first->json('refresh_token');

    expect($newRefreshToken)->not->toBe($tokens['refresh_token']);

    // Reusing the original (now-rotated) refresh token must fail...
    $reuse = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']]);
    $reuse->assertUnauthorized();

    // ...and because reuse was detected, even the *new* token should now be revoked.
    $afterCompromise = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $newRefreshToken]);
    $afterCompromise->assertUnauthorized();
});

it('invalidates the access token and revokes refresh tokens on logout', function () {
    $user = User::factory()->create(['password' => bcrypt('CorrectPass1')]);
    $tokens = loginAndGetTokens($user->email, 'CorrectPass1');

    $this->withHeader('Authorization', 'Bearer '.$tokens['access_token'])
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    // The refresh token issued at login should no longer work post-logout.
    $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
        ->assertUnauthorized();
});
