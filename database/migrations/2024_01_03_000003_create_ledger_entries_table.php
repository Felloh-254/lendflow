<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained();
            // Named `account_code`, not `account_id` as in the original
            // spec sketch — this project doesn't model a separate
            // `accounts` table (out of scope for an MVP ledger), so a
            // numeric "id" would misleadingly imply a foreign key that
            // doesn't exist. A short semantic string (see
            // App\Support\LedgerAccounts) is honest about what it is: a
            // fixed set of named ledger accounts, not a queryable table.
            $table->string('account_code');
            $table->enum('entry_type', ['debit', 'credit']);
            $table->decimal('amount', 14, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['transaction_id']);
            $table->index(['account_code']);
        });

        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_positive_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
