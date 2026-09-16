<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('principal_due', 14, 2);
            $table->decimal('interest_due', 14, 2);
            $table->decimal('total_due', 14, 2);
            $table->decimal('principal_paid', 14, 2)->default(0);
            $table->decimal('interest_paid', 14, 2)->default(0);
            $table->enum('status', ['pending', 'partially_paid', 'paid', 'overdue'])->default('pending');
            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index(['loan_id', 'status']);
            $table->index('due_date');
        });

        DB::statement('ALTER TABLE repayment_schedules ADD CONSTRAINT repayment_schedules_paid_le_due_check CHECK (principal_paid <= principal_due AND interest_paid <= interest_due)');
        DB::statement('ALTER TABLE repayment_schedules ADD CONSTRAINT repayment_schedules_non_negative_check CHECK (principal_paid >= 0 AND interest_paid >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('repayment_schedules');
    }
};
