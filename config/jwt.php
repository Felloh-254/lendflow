<?php

return [

    // Set via `php artisan jwt:secret` on first boot (see entrypoint.sh)
    'secret' => env('JWT_SECRET'),

    'keys' => [
        'public' => env('JWT_PUBLIC_KEY'),
        'private' => env('JWT_PRIVATE_KEY'),
        'passphrase' => env('JWT_PASSPHRASE'),
    ],

    // Access token lifetime, in minutes. Kept short (default 15) because
    // access tokens are stateless and cannot be revoked before expiry —
    // see docs/authentication.md for the full rotation/revocation strategy.
    'ttl' => env('JWT_TTL', 15),

    // Refresh token lifetime, in minutes (default 14 days). Refresh tokens
    // ARE tracked server-side (see App\Models\RefreshToken) so they can be
    // revoked on logout or on detected reuse.
    'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),

    'algo' => env('JWT_ALGO', 'HS256'),

    'required_claims' => [
        'iss', 'iat', 'exp', 'nbf', 'sub', 'jti',
    ],

    'persistent_claims' => [
        // Custom claims we always want carried into a refreshed token.
        'role',
    ],

    'lock_subject' => true,

    'leeway' => 0,

    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),

    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),

    'decrypt_cookies' => false,

    'providers' => [
        'jwt' => Tymon\JWTAuth\Providers\JWT\Lcobucci::class,
        'auth' => Tymon\JWTAuth\Providers\Auth\Illuminate::class,
        'storage' => Tymon\JWTAuth\Providers\Storage\Illuminate::class,
    ],

];
