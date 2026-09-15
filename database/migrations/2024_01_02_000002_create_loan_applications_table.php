<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained();
            $table->decimal('amount_requested', 14, 2);
            $table->unsignedSmallInteger('term_months');
            $table->string('purpose');
            $table->enum('status', [
                'draft', 'submitted', 'under_review', 'approved', 'rejected', 'cancelled',
            ])->default('draft');
            // Set the first time a loan officer assesses this application.
            // A loan officer may only assess/re-assess an application that
            // is unassigned or already assigned to them — see
            // LoanApplicationPolicy::assess() and docs/authorization.md.
            $table->foreignId('assigned_loan_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('assigned_loan_officer_id');
        });

        DB::statement('ALTER TABLE loan_applications ADD CONSTRAINT loan_applications_positive_amount_check CHECK (amount_requested > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
