<?php

namespace App\Services;

use App\Events\LoanApproved;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns every write to LoanApplication::status. Controllers never call
 * `$application->update(['status' => ...])` directly — every transition
 * goes through a named method here, each of which:
 *   1. Validates the transition against LoanApplication::TRANSITIONS
 *   2. Performs the change inside a DB transaction
 *   3. Records an audit log entry with the before/after status
 *
 * Doing all three consistently is the actual point of this class — the
 * individual operations are simple, but "simple and done in exactly one
 * place, every time" is what makes the lifecycle trustworthy.
 */
class LoanApplicationService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly LoanService $loans,
    ) {}

    public function create(Customer $customer, LoanProduct $product, array $data): LoanApplication
    {
        if (! $product->isAmountWithinRange((float) $data['amount_requested'])) {
            throw ValidationException::withMessages([
                'amount_requested' => "Amount must be between {$product->min_amount} and {$product->max_amount} for this product.",
            ]);
        }

        if (! $product->isTermWithinRange((int) $data['term_months'])) {
            throw ValidationException::withMessages([
                'term_months' => "Term must be between {$product->term_min} and {$product->term_max} months for this product.",
            ]);
        }

        return DB::transaction(function () use ($customer, $product, $data) {
            $application = LoanApplication::create([
                'customer_id' => $customer->id,
                'loan_product_id' => $product->id,
                'amount_requested' => $data['amount_requested'],
                'term_months' => $data['term_months'],
                'purpose' => $data['purpose'],
                'status' => LoanApplication::STATUS_DRAFT,
            ]);

            $this->auditLog->record($customer->user, 'loan_application.created', $application, null, $application->toArray());

            return $application;
        });
    }

    public function submit(LoanApplication $application, User $actor): LoanApplication
    {
        return $this->transition($application, LoanApplication::STATUS_SUBMITTED, $actor, function () use ($application) {
            $application->submitted_at = now();
        });
    }

    public function cancel(LoanApplication $application, User $actor): LoanApplication
    {
        return $this->transition($application, LoanApplication::STATUS_CANCELLED, $actor);
    }

    /**
     * A loan officer assesses the application: runs the deterministic
     * credit check, stores the result, and moves the application into
     * `under_review`. The FIRST loan officer to assess an unassigned
     * application becomes its assigned officer (LoanApplicationPolicy
     * enforces that only that officer — or an unassigned application —
     * can be assessed going forward).
     */
    public function assess(
        LoanApplication $application,
        User $actor,
        CreditAssessmentService $scoring,
    ): LoanApplication {
        $reassessingInPlace = $application->status === LoanApplication::STATUS_UNDER_REVIEW;
        $movingIntoReview = $application->canTransitionTo(LoanApplication::STATUS_UNDER_REVIEW);

        if (! $reassessingInPlace && ! $movingIntoReview) {
            throw InvalidStateTransitionException::make('LoanApplication', $application->status, LoanApplication::STATUS_UNDER_REVIEW);
        }

        return DB::transaction(function () use ($application, $actor, $scoring) {
            $customer = $application->customer;

            $result = $scoring->assess(
                monthlyIncome: (float) $customer->monthly_income,
                existingDebt: 0.0, // no existing-loans tracking yet in this phase — see docs/loan-lifecycle.md
                amountRequested: (float) $application->amount_requested,
            );

            $application->creditAssessment()->updateOrCreate(
                ['loan_application_id' => $application->id],
                [...$result, 'assessed_by' => $actor->id],
            );

            $before = $application->only(['status', 'assigned_loan_officer_id']);

            if (is_null($application->assigned_loan_officer_id)) {
                $application->assigned_loan_officer_id = $actor->id;
            }

            if ($application->status === LoanApplication::STATUS_SUBMITTED) {
                $application->status = LoanApplication::STATUS_UNDER_REVIEW;
            }

            $application->save();

            $this->auditLog->record(
                $actor,
                'loan_application.assessed',
                $application,
                $before,
                $application->only(['status', 'assigned_loan_officer_id']),
            );

            return $application->fresh(['creditAssessment']);
        });
    }

    /**
     * Approval is deliberately NOT routed through the generic
     * transition() helper below — unlike reject/cancel, approving an
     * application has a side effect that must be atomic with the status
     * change: a Loan record is created in the same transaction. If Loan
     * creation fails for any reason, the application must NOT end up
     * `approved` with no corresponding loan.
     */
    public function approve(LoanApplication $application, User $actor): LoanApplication
    {
        if (! $application->canTransitionTo(LoanApplication::STATUS_APPROVED)) {
            throw InvalidStateTransitionException::make('LoanApplication', $application->status, LoanApplication::STATUS_APPROVED);
        }

        return DB::transaction(function () use ($application, $actor) {
            $before = $application->status;

            $application->status = LoanApplication::STATUS_APPROVED;
            $application->save();

            $loan = $this->loans->createFromApplication($application, $actor);

            $this->auditLog->record(
                $actor,
                'loan_application.approved',
                $application,
                ['status' => $before],
                ['status' => LoanApplication::STATUS_APPROVED, 'loan_id' => $loan->id],
            );

            // Dispatched from inside this transaction on purpose — see
            // config/queue.php's after_commit note. Any queued job a
            // listener triggers from this event will wait for this
            // transaction to actually commit before running.
            event(new LoanApproved($application));

            return $application->fresh();
        });
    }

    public function reject(LoanApplication $application, User $actor, ?string $reason = null): LoanApplication
    {
        return $this->transition($application, LoanApplication::STATUS_REJECTED, $actor, extra: ['rejection_reason' => $reason]);
    }

    private function transition(
        LoanApplication $application,
        string $target,
        User $actor,
        ?\Closure $mutate = null,
        array $extra = [],
    ): LoanApplication {
        if (! $application->canTransitionTo($target)) {
            throw InvalidStateTransitionException::make('LoanApplication', $application->status, $target);
        }

        return DB::transaction(function () use ($application, $target, $actor, $mutate, $extra) {
            $before = $application->status;

            if ($mutate) {
                $mutate($application);
            }

            $application->status = $target;
            $application->save();

            $this->auditLog->record(
                $actor,
                "loan_application.{$target}",
                $application,
                ['status' => $before],
                ['status' => $target, ...$extra],
            );

            return $application->fresh();
        });
    }
}
