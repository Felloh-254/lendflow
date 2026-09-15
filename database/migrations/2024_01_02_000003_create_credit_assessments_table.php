<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_assessments', function (Blueprint $table) {
            $table->id();
            // One assessment per application — re-assessing (e.g. after new
            // income info) OVERWRITES this row rather than creating a new
            // one, since only the latest assessment is ever actionable. If
            // a full assessment history becomes a requirement later, this
            // is the seam where we'd switch to an audit_logs-style append
            // pattern instead.
            $table->foreignId('loan_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('credit_score');
            $table->decimal('monthly_income', 14, 2);
            $table->decimal('existing_debt', 14, 2);
            $table->decimal('debt_to_income_ratio', 5, 4);
            $table->enum('risk_level', ['low', 'medium', 'high']);
            $table->enum('recommendation', ['approve', 'reject']);
            $table->foreignId('assessed_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE credit_assessments ADD CONSTRAINT credit_assessments_score_range_check CHECK (credit_score BETWEEN 300 AND 850)');
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_assessments');
    }
};
