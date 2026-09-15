<?php

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyKeyInUseException;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Services\IdempotencyService;

beforeEach(function () {
    $this->service = new IdempotencyService;
    $this->userId = User::factory()->create()->id;
});

it('runs the operation exactly once and returns replayed=false the first time', function () {
    $calls = 0;

    $result = $this->service->handle('KEY-1', $this->userId, 'test.endpoint', ['amount' => 100], function () use (&$calls) {
        $calls++;

        return ['status' => 200, 'body' => ['ok' => true]];
    });

    expect($calls)->toBe(1);
    expect($result['replayed'])->toBeFalse();
    expect($result['body'])->toBe(['ok' => true]);
});

it('replays the stored response for a repeated call with an identical payload', function () {
    $calls = 0;
    $operation = function () use (&$calls) {
        $calls++;

        return ['status' => 201, 'body' => ['id' => 42]];
    };

    $this->service->handle('KEY-2', $this->userId, 'test.endpoint', ['amount' => 100], $operation);
    $result = $this->service->handle('KEY-2', $this->userId, 'test.endpoint', ['amount' => 100], $operation);

    expect($calls)->toBe(1); // operation only ran on the first call
    expect($result['replayed'])->toBeTrue();
    expect($result['status'])->toBe(201);
    expect($result['body'])->toBe(['id' => 42]);
});

it('throws a conflict when the same key is reused with a different payload', function () {
    $this->service->handle('KEY-3', $this->userId, 'test.endpoint', ['amount' => 100], fn () => ['status' => 200, 'body' => []]);

    expect(fn () => $this->service->handle('KEY-3', $this->userId, 'test.endpoint', ['amount' => 999], fn () => ['status' => 200, 'body' => []]))
        ->toThrow(IdempotencyConflictException::class);
});

it('allows the same key to be reused across different endpoints', function () {
    $result1 = $this->service->handle('SHARED-KEY', $this->userId, 'endpoint.a', [], fn () => ['status' => 200, 'body' => ['from' => 'a']]);
    $result2 = $this->service->handle('SHARED-KEY', $this->userId, 'endpoint.b', [], fn () => ['status' => 200, 'body' => ['from' => 'b']]);

    expect($result1['body'])->toBe(['from' => 'a']);
    expect($result2['body'])->toBe(['from' => 'b']);
});

it('deletes the record on failure so the same key can be retried', function () {
    try {
        $this->service->handle('KEY-4', $this->userId, 'test.endpoint', [], function () {
            throw new RuntimeException('operation failed');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(IdempotencyKey::where('key', 'KEY-4')->exists())->toBeFalse();

    // A retry with the same key now succeeds rather than being blocked.
    $result = $this->service->handle('KEY-4', $this->userId, 'test.endpoint', [], fn () => ['status' => 200, 'body' => ['ok' => true]]);
    expect($result['replayed'])->toBeFalse();
});

it('throws IdempotencyKeyInUseException for a concurrent request still processing under the same key', function () {
    // Simulate a request that started (row exists, still 'processing')
    // but hasn't completed yet — e.g. a genuinely concurrent second
    // request that arrived while the first was mid-flight.
    IdempotencyKey::create([
        'key' => 'KEY-5',
        'user_id' => $this->userId,
        'endpoint' => 'test.endpoint',
        'request_hash' => hash('sha256', json_encode(['amount' => 100])),
        'status' => IdempotencyKey::STATUS_PROCESSING,
    ]);

    expect(fn () => $this->service->handle('KEY-5', $this->userId, 'test.endpoint', ['amount' => 100], fn () => ['status' => 200, 'body' => []]))
        ->toThrow(IdempotencyKeyInUseException::class);
});
