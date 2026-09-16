<?php

use App\Events\LoanMarkedOverdue;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Models\Transaction;
use Illuminate\Support\Facades\Event;

function createLoanWithInstallment(array $scheduleOverrides = [], array $loanOverrides = []): array
{
    $customer = Customer::factory()->create();
    $loan = Loan::factory()->active()->create(array_merge(['customer_id' => $customer->id], $loanOverrides));

    $schedule = $loan->repaymentSchedules()->create(array_merge([
        'installment_number' => 1,
        'due_date' => now()->subDays(5)->toDateString(),
        'principal_due' => 10000,
        'interest_due' => 600,
        'total_due' => 10600,
        'status' => RepaymentSchedule::STATUS_PENDING,
    ], $scheduleOverrides));

    return [$loan, $schedule];
}

it('marks a past-due unpaid installment overdue and applies a penalty', function () {
    [$loan, $schedule] = createLoanWithInstallment();

    $this->artisan('loans:mark-overdue')->assertSuccessful();

    $schedule->refresh();
    $loan->refresh();

    expect($schedule->status)->toBe(RepaymentSchedule::STATUS_OVERDUE);
    // remaining total = 10600; 2% of that = 212, which exceeds the 100 flat fee.
    expect((float) $schedule->penalty_due)->toBe(212.0);
    expect((float) $schedule->total_due)->toBe(10812.0);

    expect($loan->status)->toBe(Loan::STATUS_OVERDUE);
    expect((float) $loan->outstanding_fees)->toBe(212.0);
});

it('applies the flat minimum fee when the percentage would be smaller', function () {
    [$loan, $schedule] = createLoanWithInstallment([
        'principal_due' => 500, 'interest_due' => 0, 'total_due' => 500,
    ]);

    $this->artisan('loans:mark-overdue')->assertSuccessful();

    // 2% of 500 = 10, which is less than the 100 flat fee — flat fee wins.
    expect((float) $schedule->refresh()->penalty_due)->toBe(100.0);
});

it('posts a fee transaction to the ledger for the penalty', function () {
    [$loan, $schedule] = createLoanWithInstallment();

    $this->artisan('loans:mark-overdue');

    $feeTransaction = Transaction::where('loan_id', $loan->id)->where('type', Transaction::TYPE_FEE)->first();
    expect($feeTransaction)->not->toBeNull();
    expect((float) $feeTransaction->amount)->toBe(212.0);

    $entries = $feeTransaction->ledgerEntries;
    expect((float) $entries->where('entry_type', 'debit')->sum('amount'))
        ->toBe((float) $entries->where('entry_type', 'credit')->sum('amount'));
});

it('dispatches LoanMarkedOverdue exactly once even when multiple installments on the same loan are overdue', function () {
    Event::fake([LoanMarkedOverdue::class]);

    $customer = Customer::factory()->create();
    $loan = Loan::factory()->active()->create(['customer_id' => $customer->id]);
    $loan->repaymentSchedules()->create([
        'installment_number' => 1, 'due_date' => now()->subDays(10)->toDateString(),
        'principal_due' => 5000, 'interest_due' => 300, 'total_due' => 5300, 'status' => RepaymentSchedule::STATUS_PENDING,
    ]);
    $loan->repaymentSchedules()->create([
        'installment_number' => 2, 'due_date' => now()->subDays(5)->toDateString(),
        'principal_due' => 5000, 'interest_due' => 300, 'total_due' => 5300, 'status' => RepaymentSchedule::STATUS_PENDING,
    ]);

    $this->artisan('loans:mark-overdue');

    Event::assertDispatchedTimes(LoanMarkedOverdue::class, 1);
});

it('leaves installments that are not yet due untouched', function () {
    [$loan, $schedule] = createLoanWithInstallment(['due_date' => now()->addDays(5)->toDateString()]);

    $this->artisan('loans:mark-overdue');

    expect($schedule->refresh()->status)->toBe(RepaymentSchedule::STATUS_PENDING);
    expect((float) $schedule->refresh()->penalty_due)->toBe(0.0);
    expect($loan->refresh()->status)->toBe(Loan::STATUS_ACTIVE);
});

it('leaves a fully paid installment untouched even if its due date has passed', function () {
    [$loan, $schedule] = createLoanWithInstallment([
        'status' => RepaymentSchedule::STATUS_PAID,
        'principal_paid' => 10000, 'interest_paid' => 600,
    ]);

    $this->artisan('loans:mark-overdue');

    expect($schedule->refresh()->status)->toBe(RepaymentSchedule::STATUS_PAID);
    expect((float) $schedule->refresh()->penalty_due)->toBe(0.0);
});

it('is idempotent: running the job twice does not double-apply the penalty', function () {
    [$loan, $schedule] = createLoanWithInstallment();

    $this->artisan('loans:mark-overdue');
    $penaltyAfterFirstRun = (float) $schedule->refresh()->penalty_due;

    $this->artisan('loans:mark-overdue');
    $penaltyAfterSecondRun = (float) $schedule->refresh()->penalty_due;

    expect($penaltyAfterSecondRun)->toBe($penaltyAfterFirstRun);
    expect(Transaction::where('loan_id', $loan->id)->where('type', Transaction::TYPE_FEE)->count())->toBe(1);
    expect((float) $loan->refresh()->outstanding_fees)->toBe($penaltyAfterFirstRun);
});

it('still marks an installment overdue if it was previously penalized and partially repaid', function () {
    // Simulates: job ran once (penalty applied, status=overdue), customer
    // then made a partial payment which reset status to partially_paid —
    // the penalty_due guard (not status) must still prevent re-penalizing.
    [$loan, $schedule] = createLoanWithInstallment([
        'status' => RepaymentSchedule::STATUS_PARTIALLY_PAID,
        'penalty_due' => 212,
        'penalty_paid' => 212,
        'principal_paid' => 5000,
    ]);

    $this->artisan('loans:mark-overdue');

    // Penalty is untouched — already applied once, never re-applied.
    expect((float) $schedule->refresh()->penalty_due)->toBe(212.0);
    expect(Transaction::where('loan_id', $loan->id)->where('type', Transaction::TYPE_FEE)->count())->toBe(0);
});

it('reports a correct summary count', function () {
    createLoanWithInstallment();
    createLoanWithInstallment();

    $this->artisan('loans:mark-overdue')
        ->expectsOutputToContain('Processed 2 overdue installment(s); 2 loan(s) newly marked overdue.')
        ->assertSuccessful();
});
