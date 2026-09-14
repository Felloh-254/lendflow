<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A pure JWT refresh token can't be revoked before it expires — anyone who
 * steals it can keep using it for the full refresh TTL. We close that gap
 * by tracking a hash of each issued refresh token server-side: logout,
 * password change, or detected reuse can all invalidate it immediately.
 */
class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return is_null($this->revoked_at) && $this->expires_at->isFuture();
    }
}
