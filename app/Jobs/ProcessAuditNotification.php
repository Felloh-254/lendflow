<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Distinct from AuditLogService: that writes the permanent, queryable
 * `audit_logs` row SYNCHRONOUSLY inside the same transaction as the
 * change it describes (see docs/loan-lifecycle.md) — it has to be, since
 * an audit record for something that didn't actually happen would be
 * worse than no record.
 *
 * This job is a best-effort, ASYNCHRONOUS notification — e.g. posting to
 * an ops/compliance Slack channel about a large disbursement — where
 * losing the occasional notification to a transient failure is an
 * acceptable tradeoff for not blocking the HTTP response on it. If this
 * job fails after its retries, the audit trail itself is completely
 * unaffected; only the "someone got pinged about it" side effect is
 * missing.
 */
class ProcessAuditNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $eventType,
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly array $context = [],
    ) {}

    public function handle(): void
    {
        Log::info('[audit-notification] '.$this->eventType, [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'context' => $this->context,
            'channel' => 'ops/compliance Slack (simulated)',
        ]);
    }
}
