<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Refresh tokens are opaque, random strings — unlike access tokens they
 * carry no claims. We store only a SHA-256 hash of the token server-side
 * (same rationale as password hashing) so a database leak alone can't be
 * used to impersonate a user.
 *
 * Rotation-on-use: every successful refresh issues a brand new token and
 * immediately revokes the one that was presented. If a refresh token is
 * ever reused after rotation, that's a strong signal of theft — we treat
 * it as a compromise event and revoke every refresh token for that user.
 */
class RefreshTokenService
{
    public function issue(User $user): string
    {
        $plainToken = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes((int) config('jwt.refresh_ttl')),
        ]);

        return $plainToken;
    }

    /**
     * Validate a presented refresh token, rotate it, and return the new
     * plain-text token alongside the user it belongs to.
     *
     * @throws \RuntimeException when the token is invalid, expired, or reused
     */
    public function rotate(string $plainToken): array
    {
        return DB::transaction(function () use ($plainToken) {
            $hash = hash('sha256', $plainToken);

            /** @var RefreshToken|null $record */
            $record = RefreshToken::where('token_hash', $hash)->lockForUpdate()->first();

            if (! $record) {
                throw new \RuntimeException('Refresh token not recognized.');
            }

            if (! is_null($record->revoked_at)) {
                // Reuse of an already-rotated token — treat as compromise.
                $this->revokeAllForUser($record->user_id);
                throw new \RuntimeException('Refresh token has already been used. All sessions for this account have been revoked.');
            }

            if ($record->expires_at->isPast()) {
                throw new \RuntimeException('Refresh token has expired.');
            }

            $record->update(['revoked_at' => now()]);

            $user = $record->user;
            $newToken = $this->issue($user);

            return [$user, $newToken];
        });
    }

    public function revoke(string $plainToken): void
    {
        RefreshToken::where('token_hash', hash('sha256', $plainToken))
            ->update(['revoked_at' => now()]);
    }

    public function revokeAllForUser(int $userId): void
    {
        RefreshToken::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
