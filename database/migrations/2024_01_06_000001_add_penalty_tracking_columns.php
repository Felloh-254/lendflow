<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repayment_schedules', function ($table) {
            // Penalty applied for THIS installment being overdue. Tracked
            // separately from principal_due/interest_due (rather than
            // folded into total_due) so the original repayment plan stays
            // intact and auditable — a penalty is a distinct, later-added
            // charge, not a retroactive change to what was originally
            // scheduled.
            $table->decimal('penalty_due', 14, 2)->default(0)->after('interest_due');
            $table->decimal('penalty_paid', 14, 2)->default(0)->after('principal_paid');
        });

        Schema::table('loans', function ($table) {
            $table->decimal('outstanding_fees', 14, 2)->default(0)->after('outstanding_interest');
        });

        DB::statement('ALTER TABLE repayment_schedules ADD CONSTRAINT repayment_schedules_penalty_paid_le_due_check CHECK (penalty_paid <= penalty_due)');
        DB::statement('ALTER TABLE repayment_schedules ADD CONSTRAINT repayment_schedules_penalty_non_negative_check CHECK (penalty_due >= 0 AND penalty_paid >= 0)');
        DB::statement('ALTER TABLE loans ADD CONSTRAINT loans_outstanding_fees_non_negative_check CHECK (outstanding_fees >= 0)');
    }

    public function down(): void
    {
        Schema::table('repayment_schedules', function ($table) {
            $table->dropColumn(['penalty_due', 'penalty_paid']);
        });

        Schema::table('loans', function ($table) {
            $table->dropColumn('outstanding_fees');
        });
    }
};
