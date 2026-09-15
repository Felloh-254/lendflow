<?php

use App\Models\Loan;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;

it('disburses an approved loan and posts a balanced ledger transaction', function () {
    $manager = User::factory()->manager()->create();
    $loan = Loan::factory()->create(['principal_amount' => 50000, 'status' => Loan::STATUS_APPROVED]);

    $response = $this->withHeaders($this->apiHeaders($manager))
        ->withHeader('Idempotency-Key', 'DISBURSE-TEST-1')
        ->postJson("/api/v1/loans/{$loan->id}/disburse");

    $response->assertOk()->assertJsonPath('status', Loan::STATUS_ACTIVE);

    $loan->refresh();
    expect($loan->status)->toBe(Loan::STATUS_ACTIVE);
    expect($loan->disbursed_at)->not->toBeNull();

    $transaction = Transaction::where('loan_id', $loan->id)->where('type', Transaction::TYPE_DISBURSEMENT)->first();
    expect($transaction)->not->toBeNull();
    expect((float) $transaction->amount)->toBe(50000.0);

    $entries = LedgerEntry::where('transaction_id', $transaction->id)->get();
    expect($entries)->toHaveCount(2);

    $debits = $entries->where('entry_type', 'debit')->sum('amount');
    $credits = $entries->where('entry_type', 'credit')->sum('amount');
    expect((float) $debits)->toBe((float) $credits);
    expect((float) $debits)->toBe(50000.0);
});

it('requires an Idempotency-Key header on disbursement', function () {
    $manager = User::factory()->manager()->create();
    $loan = Loan::factory()->create(['status' => Loan::STATUS_APPROVED]);

    $this->withHeaders($this->apiHeaders($manager))
        ->postJson("/api/v1/loans/{$loan->id}/disburse")
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'idempotency_key_missing');
});

it('replays the original response for a repeated disbursement request with the same key', function () {
    $manager = User::factory()->manager()->create();
    $loan = Loan::factory()->create(['status' => Loan::STATUS_APPROVED]);

    $first = $this->withHeaders($this->apiHeaders($manager))
        ->withHeader('Idempotency-Key', 'DISBURSE-REPLAY-1')
        ->postJson("/api/v1/loans/{$loan->id}/disburse")
        ->assertOk();

    expect($first->headers->get('Idempotent-Replayed'))->toBe('false');

    $second = $this->withHeaders($this->apiHeaders($manager))
        ->withHeader('Idempotency-Key', 'DISBURSE-REPLAY-1')
        ->postJson("/api/v1/loans/{$loan->id}/disburse")
        ->assertOk();

    expect($second->headers->get('Idempotent-Replayed'))->toBe('true');

    // Only ONE disbursement transaction should exist, despite two requests.
    expect(Transaction::where('loan_id', $loan->id)->count())->toBe(1);
});

it('prevents disbursing an already-active loan even with a new idempotency key', function () {
    $manager = User::factory()->manager()->create();
    $loan = Loan::factory()->active()->create();

    $this->withHeaders($this->apiHeaders($manager))
        ->withHeader('Idempotency-Key', 'DISBURSE-ALREADY-ACTIVE')
        ->postJson("/api/v1/loans/{$loan->id}/disburse")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_state_transition');
});

it('forbids a loan officer from disbursing a loan', function () {
    $officer = User::factory()->loanOfficer()->create();
    $loan = Loan::factory()->create(['status' => Loan::STATUS_APPROVED]);

    $this->withHeaders($this->apiHeaders($officer))
        ->withHeader('Idempotency-Key', 'DISBURSE-FORBIDDEN')
        ->postJson("/api/v1/loans/{$loan->id}/disburse")
        ->assertForbidden();
});

it('forbids a customer from disbursing any loan, including their own', function () {
    $user = User::factory()->create();
    $customer = \App\Models\Customer::factory()->create(['user_id' => $user->id]);
    $loan = Loan::factory()->create(['customer_id' => $customer->id, 'status' => Loan::STATUS_APPROVED]);

    $this->withHeaders($this->apiHeaders($user))
        ->withHeader('Idempotency-Key', 'DISBURSE-CUSTOMER-FORBIDDEN')
        ->postJson("/api/v1/loans/{$loan->id}/disburse")
        ->assertForbidden();
});
