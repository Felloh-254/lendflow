<?php

use App\Events\LoanApproved;
use App\Events\LoanCompleted;
use App\Events\LoanDisbursed;
use App\Events\RepaymentReceived;
use App\Jobs\GenerateStatement;
use App\Jobs\ProcessAuditNotification;
use App\Jobs\SendLoanApprovalNotification;
use App\Jobs\SendRepaymentConfirmation;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('dispatches LoanApproved and queues the approval notification when an application is approved', function () {
    Queue::fake();

    $customer = Customer::factory()->create();
    $application = LoanApplication::factory()->underReview()->create(['customer_id' => $customer->id]);
    $manager = User::factory()->manager()->create();

    $this->withHeaders($this->apiHeaders($manager))
        ->postJson("/api/v1/loan-applications/{$application->id}/approve")
        ->assertOk();

    Queue::assertPushed(SendLoanApprovalNotification::class, function ($job) use ($application) {
        return $job->loanApplication->id === $application->id;
    });

    Queue::assertPushed(ProcessAuditNotification::class, function ($job) {
        return $job->eventType === 'loan_application.approved';
    });
});

it('dispatches LoanDisbursed and queues an audit notification on disbursement', function () {
    Queue::fake();

    $manager = User::factory()->manager()->create();
    $loan = Loan::factory()->create(['status' => Loan::STATUS_APPROVED]);

    $this->withHeaders($this->apiHeaders($manager))
        ->withHeader('Idempotency-Key', 'EVENT-DISBURSE-1')
        ->postJson("/api/v1/loans/{$loan->id}/disburse")
        ->assertOk();

    Queue::assertPushed(ProcessAuditNotification::class, function ($job) use ($loan) {
        return $job->eventType === 'loan.disbursed' && $job->entityId === $loan->id;
    });
});

it('dispatches RepaymentReceived and queues a confirmation for every repayment', function () {
    Queue::fake();

    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $loan = Loan::factory()->active()->create(['customer_id' => $customer->id, 'outstanding_principal' => 10000, 'outstanding_interest' => 0]);
    $loan->repaymentSchedules()->create([
        'installment_number' => 1, 'due_date' => now()->addMonth(),
        'principal_due' => 10000, 'interest_due' => 0, 'total_due' => 10000,
        'status' => \App\Models\RepaymentSchedule::STATUS_PENDING,
    ]);

    $this->withHeaders($this->apiHeaders($user))
        ->withHeader('Idempotency-Key', 'EVENT-REPAY-1')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 5000, 'payment_method' => 'mpesa'])
        ->assertCreated();

    Queue::assertPushed(SendRepaymentConfirmation::class);

    // Not fully repaid yet — LoanCompleted should NOT have fired.
    Queue::assertNotPushed(GenerateStatement::class);
});

it('dispatches LoanCompleted and queues statement generation only once the loan is fully repaid', function () {
    Queue::fake();

    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $loan = Loan::factory()->active()->create(['customer_id' => $customer->id, 'outstanding_principal' => 10000, 'outstanding_interest' => 0]);
    $loan->repaymentSchedules()->create([
        'installment_number' => 1, 'due_date' => now()->addMonth(),
        'principal_due' => 10000, 'interest_due' => 0, 'total_due' => 10000,
        'status' => \App\Models\RepaymentSchedule::STATUS_PENDING,
    ]);

    $this->withHeaders($this->apiHeaders($user))
        ->withHeader('Idempotency-Key', 'EVENT-REPAY-FULL')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 10000, 'payment_method' => 'mpesa'])
        ->assertCreated();

    Queue::assertPushed(GenerateStatement::class, fn ($job) => $job->loan->id === $loan->id);
    Queue::assertPushed(ProcessAuditNotification::class, fn ($job) => $job->eventType === 'loan.completed');
});

it('never queues a job for an operation that fails and rolls back', function () {
    Queue::fake();

    $manager = User::factory()->manager()->create();
    // Draft application — approving it is an illegal transition and
    // throws before the transaction can commit anything.
    $application = LoanApplication::factory()->create();

    $this->withHeaders($this->apiHeaders($manager))
        ->postJson("/api/v1/loan-applications/{$application->id}/approve")
        ->assertStatus(409);

    Queue::assertNothingPushed();
});
