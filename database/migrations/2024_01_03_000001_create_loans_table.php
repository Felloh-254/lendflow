<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('loan_application_id')->unique()->constrained();
            $table->foreignId('loan_product_id')->constrained();
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('interest_amount', 14, 2);
            $table->decimal('total_amount', 14, 2);
            $table->decimal('outstanding_principal', 14, 2);
            $table->decimal('outstanding_interest', 14, 2);
            $table->enum('status', [
                'approved', 'pending_disbursement', 'active', 'overdue', 'completed', 'defaulted', 'cancelled',
            ])->default('approved');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('customer_id');
        });

        // Outstanding balances can never go negative — this is the last
        // line of defense against a bug in RepaymentService ever
        // overpaying a loan, even under concurrent load. Application-level
        // checks (Phase 8) are the first line; this constraint is what
        // makes an overpay bug fail loudly (a DB error) instead of
        // silently producing a nonsensical negative balance.
        DB::statement('ALTER TABLE loans ADD CONSTRAINT loans_outstanding_principal_non_negative CHECK (outstanding_principal >= 0)');
        DB::statement('ALTER TABLE loans ADD CONSTRAINT loans_outstanding_interest_non_negative CHECK (outstanding_interest >= 0)');
        DB::statement('ALTER TABLE loans ADD CONSTRAINT loans_positive_principal CHECK (principal_amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
