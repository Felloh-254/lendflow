<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use Illuminate\Database\QueryException;

/**
 * Why idempotency matters here: `POST /loans/{id}/disburse` and
 * `POST /loans/{id}/repayments` move real money. A client retrying after
 * a timeout — never knowing whether the first request actually landed —
 * must not be able to trigger a second disbursement or a second
 * repayment. An Idempotency-Key header lets the client say "this is the
 * same logical operation I already tried", and this service guarantees
 * that operation only ever executes once.
 *
 * The concurrency-safety property this relies on: the very first thing
 * `handle()` does is an INSERT into `idempotency_keys`, which carries a
 * UNIQUE constraint on (key, endpoint, user_id). If two requests with the
 * same key arrive genuinely simultaneously, the database — not
 * application code — guarantees only one INSERT can succeed; the other
 * fails immediately with a unique-violation error. There is no
 * SELECT-then-INSERT window for both requests to race through, which is
 * exactly the class of bug docs/concurrency.md documents for repayments.
 */
class IdempotencyService
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(): array{status: int, body: mixed}  $operation
     * @return array{status: int, body: mixed, replayed: bool}
     */
    public function handle(?string $key, ?int $userId, string $endpoint, array $payload, callable $operation): array
    {
        if (blank($key)) {
            throw new \InvalidArgumentException('An Idempotency-Key is required for this operation.');
        }

        $hash = $this->hashPayload($payload);

        try {
            $record = IdempotencyKey::create([
                'key' => $key,
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'request_hash' => $hash,
                'status' => IdempotencyKey::STATUS_PROCESSING,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return $this->resolveExisting($key, $endpoint, $userId, $hash);
            }

            throw $e;
        }

        try {
            $result = $operation();
        } catch (\Throwable $e) {
            // Don't leave a stuck 'processing' row behind on failure —
            // that would permanently block retries with this same key.
            // A failed attempt should be retryable.
            $record->delete();

            throw $e;
        }

        $record->update([
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'response_status' => $result['status'],
            'response_body' => $result['body'],
        ]);

        return [...$result, 'replayed' => false];
    }

    private function resolveExisting(string $key, string $endpoint, ?int $userId, string $hash): array
    {
        $existing = IdempotencyKey::where('key', $key)
            ->where('endpoint', $endpoint)
            ->where('user_id', $userId)
            ->first();

        if (! $existing) {
            // Vanishingly unlikely (the row that caused our unique
            // violation was deleted between our INSERT failing and this
            // lookup) — safe to treat as "no record", so the caller can
            // retry with a fresh attempt.
            throw new \RuntimeException('Idempotency record could not be resolved. Please retry.');
        }

        if ($existing->request_hash !== $hash) {
            throw IdempotencyConflictException::make($key);
        }

        if ($existing->status === IdempotencyKey::STATUS_PROCESSING) {
            throw \App\Exceptions\IdempotencyKeyInUseException::make($key);
        }

        return [
            'status' => $existing->response_status,
            'body' => $existing->response_body,
            'replayed' => true,
        ];
    }

    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // Postgres SQLSTATE 23505 = unique_violation.
        return $e->getCode() === '23505' || str_contains($e->getMessage(), 'duplicate key value');
    }
}
