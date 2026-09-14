<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mint a real JWT for the given user and return it as a ready-to-use
     * Authorization header. Used instead of Laravel's session-based
     * `actingAs()`, which the stateless JwtAuthenticate middleware doesn't
     * recognize — every protected-route test goes through the same token
     * parsing path a real client would.
     */
    protected function apiHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
