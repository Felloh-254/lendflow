<?php

use App\Models\Loan;
use Tests\Support\ConcurrentProcess;

/**
 * See docs/deadlocks.md for the full narrative. Short version: two
 * transactions each need to lock the SAME two loan rows, but acquire
 * them in opposite order — the classic precondition for a deadlock.
 * PostgreSQL's own deadlock detector catches the circular wait and
 * aborts one of the two transactions so the other can proceed.
 */
function createTwoLoans(): array
{
    $customer = \App\Models\Customer::factory()->create();

    return [
        Loan::factory()->active()->create(['customer_id' => $customer->id]),
        Loan::factory()->active()->create(['customer_id' => $customer->id]),
    ];
}

it('BEFORE THE FIX: inconsistent lock order between two transactions produces a real, detected deadlock', function () {
    [$loanA, $loanB] = createTwoLoans();

    // Process 1 locks A then B. Process 2 locks B then A. Each holds its
    // first lock for 1s before attempting the second — long enough to
    // guarantee both processes are holding one lock and waiting on the
    // other's at the same time, which is the precondition for deadlock.
    $process1 = ConcurrentProcess::artisan([
        'deadlock:simulate', (string) $loanA->id, (string) $loanB->id, '--hold-ms=1000',
    ]);
    $process2 = ConcurrentProcess::artisan([
        'deadlock:simulate', (string) $loanA->id, (string) $loanB->id, '--reverse', '--hold-ms=1000',
    ]);

    $process1->start();
    usleep(300_000); // ensure process1 has already acquired its first lock
    $process2->start();
    $process1->wait();
    $process2->wait();

    $result1 = ConcurrentProcess::lastJsonLine($process1);
    $result2 = ConcurrentProcess::lastJsonLine($process2);

    $deadlockedCount = collect([$result1, $result2])->where('deadlock_detected', true)->count();
    $succeededCount = collect([$result1, $result2])->where('success', true)->count();

    // PostgreSQL always resolves a real deadlock by aborting exactly one
    // of the two transactions — the "victim" — and letting the other
    // complete. Which one is chosen isn't something either process
    // controls, so we assert on the SHAPE of the outcome, not on which
    // specific process "won".
    expect($deadlockedCount)->toBe(1);
    expect($succeededCount)->toBe(1);
})->group('concurrency');

it('AFTER THE FIX: consistent lock ordering eliminates the deadlock entirely, even under the same contention', function () {
    [$loanA, $loanB] = createTwoLoans();

    // Both processes now request --consistent-order, which ignores
    // --reverse and always locks the lower loan ID first. Same
    // contention pattern as the broken test above — same two loans, same
    // timing — but now there is no possible circular wait.
    $process1 = ConcurrentProcess::artisan([
        'deadlock:simulate', (string) $loanA->id, (string) $loanB->id, '--consistent-order', '--hold-ms=500',
    ]);
    $process2 = ConcurrentProcess::artisan([
        'deadlock:simulate', (string) $loanA->id, (string) $loanB->id, '--reverse', '--consistent-order', '--hold-ms=500',
    ]);

    $process1->start();
    usleep(200_000);
    $process2->start();
    $process1->wait();
    $process2->wait();

    $result1 = ConcurrentProcess::lastJsonLine($process1);
    $result2 = ConcurrentProcess::lastJsonLine($process2);

    // Both succeed — process2 simply waits for process1 to release its
    // locks (a brief delay, not a deadlock), then proceeds normally.
    expect($result1['success'] ?? null)->toBeTrue();
    expect($result2['success'] ?? null)->toBeTrue();
    expect($result1['deadlock_detected'] ?? false)->toBeFalse();
    expect($result2['deadlock_detected'] ?? false)->toBeFalse();
})->group('concurrency');

it('MITIGATION 2: a deadlock victim that retries with backoff succeeds on a later attempt', function () {
    [$loanA, $loanB] = createTwoLoans();

    // Same inconsistent-order setup as the "before" test — a real
    // deadlock will occur — but both processes now retry on
    // deadlock_detected instead of giving up immediately.
    $process1 = ConcurrentProcess::artisan([
        'deadlock:simulate', (string) $loanA->id, (string) $loanB->id, '--retry', '--hold-ms=800',
    ]);
    $process2 = ConcurrentProcess::artisan([
        'deadlock:simulate', (string) $loanA->id, (string) $loanB->id, '--reverse', '--retry', '--hold-ms=800',
    ]);

    $process1->start();
    usleep(300_000);
    $process2->start();
    $process1->wait();
    $process2->wait();

    $result1 = ConcurrentProcess::lastJsonLine($process1);
    $result2 = ConcurrentProcess::lastJsonLine($process2);

    // With retries available, BOTH processes ultimately succeed — the
    // one that was chosen as the deadlock victim simply tries again
    // (a fresh transaction can now acquire both locks cleanly, since the
    // "winner" already committed and released them) rather than
    // surfacing the failure to its caller.
    expect($result1['success'] ?? null)->toBeTrue();
    expect($result2['success'] ?? null)->toBeTrue();

    // At least one of the two needed more than a single attempt — that's
    // the retry actually having done something, not a no-op.
    $maxAttempts = max($result1['attempts'] ?? 1, $result2['attempts'] ?? 1);
    expect($maxAttempts)->toBeGreaterThan(1);
})->group('concurrency');
