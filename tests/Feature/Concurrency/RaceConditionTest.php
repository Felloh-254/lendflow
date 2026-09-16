<?php

use App\Models\Loan;
use App\Models\Repayment;
use App\Models\Transaction;
use Tests\Support\ConcurrentProcess;

/**
 * See docs/race-conditions.md for the full narrative. Short version:
 * two real, independent OS processes attempt a 10,000 repayment each
 * against the same loan carrying exactly 10,000 of outstanding
 * principal, at (as near as this harness can arrange) the same instant.
 * Exactly one of them should succeed.
 */
function createSingleInstallmentLoan(float $principal): Loan
{
    $customer = \App\Models\Customer::factory()->create();

    $loan = Loan::factory()->active()->create([
        'customer_id' => $customer->id,
        'principal_amount' => $principal,
        'interest_amount' => 0,
        'total_amount' => $principal,
        'outstanding_principal' => $principal,
        'outstanding_interest' => 0,
    ]);

    // RepaymentService allocates against repayment_schedules rows, not
    // the loan's outstanding_* columns directly (see
    // docs/repayment-allocation.md) — so the race-condition demonstration
    // needs a real schedule to allocate against, exactly as disbursement
    // would have generated one.
    $loan->repaymentSchedules()->create([
        'installment_number' => 1,
        'due_date' => now()->addMonth()->toDateString(),
        'principal_due' => $principal,
        'interest_due' => 0,
        'total_due' => $principal,
        'status' => \App\Models\RepaymentSchedule::STATUS_PENDING,
    ]);

    return $loan;
}

it('BEFORE THE FIX: an unlocked read-then-write repayment path allows a double-spend under real concurrency', function () {
    $loan = createSingleInstallmentLoan(10000);

    // Process A: reads the balance, then HOLDS the transaction open for
    // 800ms before writing — giving Process B a wide, guaranteed window
    // to read the same stale balance before A commits.
    $processA = ConcurrentProcess::artisan([
        'repayment:simulate', (string) $loan->id, '10000', '--unsafe', '--hold-ms=1500',
    ]);
    $processB = ConcurrentProcess::artisan([
        'repayment:simulate', (string) $loan->id, '10000', '--unsafe',
    ]);

    // Start A, give it just enough time to have read the balance and
    // entered its hold, then start B — this is what guarantees genuine
    // overlap rather than hoping OS scheduling happens to produce it.
    $processA->start();
    usleep(400_000);
    $processB->start();
    $processA->wait();
    $processB->wait();

    $resultA = ConcurrentProcess::lastJsonLine($processA);
    $resultB = ConcurrentProcess::lastJsonLine($processB);

    // The bug: BOTH processes believed their payment was valid, because
    // both read outstanding_principal = 10,000 before either had written
    // anything back.
    expect($resultA['success'] ?? null)->toBeTrue();
    expect($resultB['success'] ?? null)->toBeTrue();
    expect($resultA['balance_read'])->toBe(10000.0);
    expect($resultB['balance_read'])->toBe(10000.0); // <- stale read, should have seen A's write

    $loan->refresh();

    // The institution now has TWO completed repayment records for
    // 10,000 each (20,000 total "collected")...
    expect(Repayment::where('loan_id', $loan->id)->count())->toBe(2);
    expect((float) Repayment::where('loan_id', $loan->id)->sum('amount'))->toBe(20000.0);

    // ...but the loan balance only ever reflects ONE of those payments,
    // because both writes were computed from the same stale read
    // (10,000 - 10,000 = 0, written twice) — this is a classic lost
    // update. The ledger and the loan balance now disagree about how
    // much was actually paid down.
    expect((float) $loan->outstanding_principal)->toBe(0.0);
})->group('concurrency');

it('AFTER THE FIX: RepaymentService with lockForUpdate() correctly rejects the second concurrent repayment', function () {
    $loan = createSingleInstallmentLoan(10000);

    // Same shape as the broken test above: Process A holds the lock open
    // for 800ms; Process B is started 150ms later, guaranteeing its
    // lockForUpdate() call happens while A still holds the row lock.
    $processA = ConcurrentProcess::artisan([
        'repayment:simulate', (string) $loan->id, '10000', '--hold-ms=1500',
    ]);
    $processB = ConcurrentProcess::artisan([
        'repayment:simulate', (string) $loan->id, '10000',
    ]);

    $processA->start();
    usleep(400_000);
    $processB->start();
    $processA->wait();
    $processB->wait();

    $resultA = ConcurrentProcess::lastJsonLine($processA);
    $resultB = ConcurrentProcess::lastJsonLine($processB);

    $successes = collect([$resultA, $resultB])->where('success', true);
    $failures = collect([$resultA, $resultB])->where('success', false);

    // Exactly one of the two succeeds. Process B's lockForUpdate() call
    // blocked until A committed, then re-read the ALREADY-UPDATED
    // balance (0) and correctly refused to accept a repayment that
    // exceeds it.
    expect($successes)->toHaveCount(1);
    expect($failures)->toHaveCount(1);
    expect($failures->first()['error'])->toContain('exceeds outstanding balance');

    $loan->refresh();

    // Exactly ONE repayment record exists, and the loan is correctly and
    // fully paid off — no lost update, no phantom double-collection.
    expect(Repayment::where('loan_id', $loan->id)->count())->toBe(1);
    expect((float) $loan->outstanding_principal)->toBe(0.0);
    expect($loan->status)->toBe(Loan::STATUS_COMPLETED);
})->group('concurrency');

it('AFTER THE FIX: ten concurrent 2,000 repayments against a 10,000 balance settle to exactly zero, never negative', function () {
    $loan = createSingleInstallmentLoan(10000);

    $processes = [];
    for ($i = 0; $i < 10; $i++) {
        $processes[] = ConcurrentProcess::artisan([
            'repayment:simulate', (string) $loan->id, '2000', '--hold-ms=100',
        ]);
    }

    ConcurrentProcess::runConcurrently($processes);

    $results = array_map(fn ($p) => ConcurrentProcess::lastJsonLine($p), $processes);
    $successCount = count(array_filter($results, fn ($r) => $r['success'] ?? false));

    // Exactly 5 of the 10 requests (5 x 2,000 = 10,000) should succeed;
    // the rest correctly fail once the balance is exhausted. The precise
    // ORDER in which they succeed isn't deterministic under real
    // concurrency (and shouldn't be asserted on) — but the FINAL STATE
    // must be exact, every time this test runs.
    expect($successCount)->toBe(5);

    $loan->refresh();
    expect((float) $loan->outstanding_principal)->toBe(0.0);
    expect((float) $loan->outstanding_principal)->toBeGreaterThanOrEqual(0.0); // never negative
    expect(Repayment::where('loan_id', $loan->id)->count())->toBe(5);
})->group('concurrency');
