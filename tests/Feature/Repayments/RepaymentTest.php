<?php

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;
use App\Models\Transaction;
use App\Models\User;

/**
 * Sets up a fully active loan via the real HTTP lifecycle (apply -> submit
 * -> assess -> approve -> disburse) so repayment tests exercise the same
 * code paths a real client would hit, rather than poking the database
 * directly. Principal 60,000 over 6 months at 12% => 10,000 principal +
 * 600 interest per installment — round numbers, easy to verify by hand.
 */
function setUpActiveLoan(): array
{
    $customerUser = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $customerUser->id, 'monthly_income' => 100000]);
    $officer = User::factory()->loanOfficer()->create();
    $manager = User::factory()->manager()->create();
    $product = LoanProduct::factory()->create(['interest_rate' => 12]);

    $application = LoanApplication::factory()->create([
        'customer_id' => $customer->id,
        'loan_product_id' => $product->id,
        'amount_requested' => 60000,
        'term_months' => 6,
    ]);

    test()->withHeaders(test()->apiHeaders($customerUser))->postJson("/api/v1/loan-applications/{$application->id}/submit")->assertOk();
    test()->withHeaders(test()->apiHeaders($officer))->postJson("/api/v1/loan-applications/{$application->id}/assess")->assertOk();
    test()->withHeaders(test()->apiHeaders($manager))->postJson("/api/v1/loan-applications/{$application->id}/approve")->assertOk();

    $loan = Loan::where('loan_application_id', $application->id)->first();

    test()->withHeaders(test()->apiHeaders($manager))
        ->withHeader('Idempotency-Key', 'SETUP-DISBURSE-'.$loan->id)
        ->postJson("/api/v1/loans/{$loan->id}/disburse")
        ->assertOk();

    return [$customerUser, $customer, $loan->fresh()];
}

it('applies a full single-installment repayment to principal and interest correctly', function () {
    [$customerUser, , $loan] = setUpActiveLoan();

    $response = $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-1')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", [
            'amount' => 10600, // exactly the first installment
            'payment_method' => 'mpesa',
        ]);

    $response->assertCreated();

    $loan->refresh();
    expect((float) $loan->outstanding_principal)->toBe(50000.0);
    expect((float) $loan->outstanding_interest)->toBe(3000.0);

    $firstInstallment = RepaymentSchedule::where('loan_id', $loan->id)->where('installment_number', 1)->first();
    expect($firstInstallment->status)->toBe(RepaymentSchedule::STATUS_PAID);
    expect((float) $firstInstallment->principal_paid)->toBe(10000.0);
    expect((float) $firstInstallment->interest_paid)->toBe(600.0);
});

it('applies a partial repayment, paying interest before principal on the earliest installment', function () {
    [$customerUser, , $loan] = setUpActiveLoan();

    $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-PARTIAL')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 300, 'payment_method' => 'mpesa'])
        ->assertCreated();

    $firstInstallment = RepaymentSchedule::where('loan_id', $loan->id)->where('installment_number', 1)->first();

    expect((float) $firstInstallment->interest_paid)->toBe(300.0);
    expect((float) $firstInstallment->principal_paid)->toBe(0.0);
    expect($firstInstallment->status)->toBe(RepaymentSchedule::STATUS_PARTIALLY_PAID);
});

it('rolls a repayment over into the second installment once the first is fully covered', function () {
    [$customerUser, , $loan] = setUpActiveLoan();

    // 10600 (installment 1) + 500 into installment 2's interest
    $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-ROLLOVER')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 11100, 'payment_method' => 'mpesa'])
        ->assertCreated();

    $second = RepaymentSchedule::where('loan_id', $loan->id)->where('installment_number', 2)->first();
    expect((float) $second->interest_paid)->toBe(500.0);
    expect($second->status)->toBe(RepaymentSchedule::STATUS_PARTIALLY_PAID);
});

it('rejects a repayment larger than the outstanding balance', function () {
    [$customerUser, , $loan] = setUpActiveLoan();

    $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-TOO-MUCH')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 999999, 'payment_method' => 'mpesa'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'insufficient_outstanding_balance');
});

it('marks a loan completed once fully repaid', function () {
    [$customerUser, , $loan] = setUpActiveLoan();

    $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-FULL')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 63600, 'payment_method' => 'bank'])
        ->assertCreated();

    $loan->refresh();
    expect($loan->status)->toBe(Loan::STATUS_COMPLETED);
    expect((float) $loan->outstanding_principal)->toBe(0.0);
    expect((float) $loan->outstanding_interest)->toBe(0.0);
    expect($loan->completed_at)->not->toBeNull();
});

it('posts a balanced ledger transaction for a repayment', function () {
    [$customerUser, , $loan] = setUpActiveLoan();

    $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-LEDGER')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 10600, 'payment_method' => 'mpesa'])
        ->assertCreated();

    $transaction = Transaction::where('loan_id', $loan->id)->where('type', Transaction::TYPE_REPAYMENT)->first();
    $entries = $transaction->ledgerEntries;

    $debits = $entries->where('entry_type', 'debit')->sum('amount');
    $credits = $entries->where('entry_type', 'credit')->sum('amount');
    expect((float) $debits)->toBe((float) $credits);
    expect((float) $debits)->toBe(10600.0);
});

it('replays the original response for a repeated repayment request with the same key and body', function () {
    [$customerUser, , $loan] = setUpActiveLoan();

    $payload = ['amount' => 10600, 'payment_method' => 'mpesa'];

    $first = $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-REPLAY')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", $payload)
        ->assertCreated();

    $second = $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-REPLAY')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", $payload)
        ->assertCreated();

    expect($second->headers->get('Idempotent-Replayed'))->toBe('true');
    expect($second->json('id'))->toBe($first->json('id'));

    // Only ONE repayment/transaction was actually created.
    expect(\App\Models\Repayment::where('loan_id', $loan->id)->count())->toBe(1);

    $loan->refresh();
    expect((float) $loan->outstanding_principal)->toBe(50000.0); // not double-deducted
});

it('rejects a repeated key used with a different amount as a conflict, not a replay', function () {
    [$customerUser, , $loan] = setUpActiveLoan();

    $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-CONFLICT')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 5000, 'payment_method' => 'mpesa'])
        ->assertCreated();

    $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-CONFLICT')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 9999, 'payment_method' => 'mpesa'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'idempotency_key_conflict');
});

it('forbids a customer from repaying a loan that is not theirs', function () {
    [, , $loan] = setUpActiveLoan();
    $otherUser = User::factory()->create();
    Customer::factory()->create(['user_id' => $otherUser->id]);

    $this->withHeaders($this->apiHeaders($otherUser))
        ->withHeader('Idempotency-Key', 'REPAY-WRONG-CUSTOMER')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 1000, 'payment_method' => 'mpesa'])
        ->assertForbidden();
});

it('forbids staff roles from posting a repayment through the customer endpoint', function () {
    [, , $loan] = setUpActiveLoan();
    $manager = User::factory()->manager()->create();

    $this->withHeaders($this->apiHeaders($manager))
        ->withHeader('Idempotency-Key', 'REPAY-STAFF-FORBIDDEN')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", ['amount' => 1000, 'payment_method' => 'mpesa'])
        ->assertForbidden();
});

it('replays successfully on retry even when external_reference would otherwise fail uniqueness validation', function () {
    // Regression test: StoreRepaymentRequest validates external_reference
    // as unique against the repayments table. Before the fix, the
    // idempotency check ran INSIDE the controller — after validation had
    // already run — so a genuine retry with the same external_reference
    // (from the repayment the first request already created) failed
    // with 422 before ever reaching the replay logic. The check now
    // lives in EnsureIdempotencyKey middleware, which runs before
    // validation, so a detected replay short-circuits before
    // StoreRepaymentRequest is ever resolved.
    [$customerUser, , $loan] = setUpActiveLoan();

    $payload = ['amount' => 5000, 'payment_method' => 'mpesa', 'external_reference' => 'MPESA-CONF-998877'];

    $first = $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-EXT-REF-RETRY')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", $payload)
        ->assertCreated();

    $second = $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-EXT-REF-RETRY')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", $payload);

    $second->assertCreated(); // NOT 422 — this is the bug this test guards against
    expect($second->headers->get('Idempotent-Replayed'))->toBe('true');
    expect($second->json('id'))->toBe($first->json('id'));
    expect(\App\Models\Repayment::where('external_reference', 'MPESA-CONF-998877')->count())->toBe(1);
});

it('still rejects a genuinely different repayment that reuses an external_reference already used by another repayment', function () {
    // Not an idempotency case at all — a DIFFERENT idempotency key, same
    // external_reference. This must still fail validation normally; the
    // fix only changes behavior for a genuine retry of the SAME attempt.
    [$customerUser, , $loan] = setUpActiveLoan();

    $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-EXT-REF-FIRST')
        ->postJson("/api/v1/loans/{$loan->id}/repayments", [
            'amount' => 1000, 'payment_method' => 'mpesa', 'external_reference' => 'MPESA-SHARED-REF',
        ])
        ->assertCreated();

    $this->withHeaders($this->apiHeaders($customerUser))
        ->withHeader('Idempotency-Key', 'REPAY-EXT-REF-SECOND') // different key
        ->postJson("/api/v1/loans/{$loan->id}/repayments", [
            'amount' => 2000, 'payment_method' => 'mpesa', 'external_reference' => 'MPESA-SHARED-REF', // same reference
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('external_reference');
});
