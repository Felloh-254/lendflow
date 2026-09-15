<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // The spec's field list for `transactions` doesn't include a
            // loan reference, but every transaction type we have
            // (disbursement, repayment, fee, reversal) is loan-scoped, and
            // querying "all transactions for this loan" is a core need —
            // so we add it as a deliberate, documented extension rather
            // than working around its absence with joins through
            // `repayments`.
            $table->foreignId('loan_id')->constrained();
            $table->string('reference')->unique();
            $table->enum('type', ['disbursement', 'repayment', 'fee', 'reversal']);
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('pending');
            $table->string('external_reference')->nullable()->unique();
            // No `updated_at` — see App\Models\Transaction. Corrections to
            // a completed transaction happen via a new `reversal`
            // transaction, never by editing this row.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['loan_id', 'type']);
        });

        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_positive_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
