<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only by construction: `$fillable` covers every column we ever
 * write via `create()`, and we never define an `update()`/`delete()` path
 * for this model anywhere in the app. There's also no route that exposes
 * write access to /audit-logs — it's a read-only resource from the API's
 * perspective, created only from inside services as a side effect of a
 * state-changing operation.
 */
class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
