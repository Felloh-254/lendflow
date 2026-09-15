<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Every important state-changing operation records an audit event through
 * this service — never through a direct `AuditLog::create()` scattered in
 * a controller or service, so the shape of an audit entry only has to be
 * gotten right in one place.
 *
 * Audit writes happen *inside* the same DB transaction as the operation
 * they describe (services pass no special handling for this — because
 * they call `record()` from within their own `DB::transaction()` closure,
 * it's naturally atomic with the change: if the transaction rolls back,
 * so does the audit entry, which is correct — an audit log for something
 * that didn't actually happen would be worse than no log at all).
 */
class AuditLogService
{
    public function record(
        ?User $actor,
        string $action,
        Model $entity,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
        ]);
    }
}
