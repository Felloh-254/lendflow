<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            // Nullable + set only once the underlying ledger transaction
            // has actually been posted — a Repayment row can exist in a
            // `failed` state with no transaction behind it (e.g. the
            // amount exceeded the outstanding balance), and that's fine:
            // it's not itself a financial record, the Transaction is.
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->enum('payment_method', ['mpesa', 'bank', 'wallet']);
            $table->string('external_reference')->nullable()->unique();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });

        DB::statement('ALTER TABLE repayments ADD CONSTRAINT repayments_positive_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('repayments');
    }
};
