<?php

namespace App\Listeners;

use App\Events\LoanApproved;
use App\Events\LoanCompleted;
use App\Events\LoanDisbursed;
use App\Events\RepaymentReceived;
use App\Jobs\ProcessAuditNotification;
use Illuminate\Events\Dispatcher;

/**
 * An Event Subscriber groups several related listener methods into one
 * class instead of a separate Listener class per event — appropriate
 * here because all four methods do conceptually the same thing (flag a
 * significant financial event to an ops/compliance channel), just with
 * different context. `subscribe()` is where the event-to-method wiring
 * is declared; AppServiceProvider registers the subscriber itself
 * (Event::subscribe(...)), not each individual mapping.
 */
class AuditNotificationSubscriber
{
    public function handleLoanApproved(LoanApproved $event): void
    {
        ProcessAuditNotification::dispatch(
            'loan_application.approved',
            'LoanApplication',
            $event->loanApplication->id,
            ['amount_requested' => (float) $event->loanApplication->amount_requested],
        );
    }

    public function handleLoanDisbursed(LoanDisbursed $event): void
    {
        ProcessAuditNotification::dispatch(
            'loan.disbursed',
            'Loan',
            $event->loan->id,
            ['principal_amount' => (float) $event->loan->principal_amount],
        );
    }

    public function handleRepaymentReceived(RepaymentReceived $event): void
    {
        ProcessAuditNotification::dispatch(
            'repayment.received',
            'Repayment',
            $event->repayment->id,
            ['amount' => (float) $event->repayment->amount],
        );
    }

    public function handleLoanCompleted(LoanCompleted $event): void
    {
        ProcessAuditNotification::dispatch(
            'loan.completed',
            'Loan',
            $event->loan->id,
            ['total_amount' => (float) $event->loan->total_amount],
        );
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(LoanApproved::class, [self::class, 'handleLoanApproved']);
        $events->listen(LoanDisbursed::class, [self::class, 'handleLoanDisbursed']);
        $events->listen(RepaymentReceived::class, [self::class, 'handleRepaymentReceived']);
        $events->listen(LoanCompleted::class, [self::class, 'handleLoanCompleted']);
    }
}
