<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('min_amount', 14, 2);
            $table->decimal('max_amount', 14, 2);
            // Annual interest rate as a percentage, e.g. 12.50 = 12.5%/year.
            $table->decimal('interest_rate', 5, 2);
            $table->unsignedSmallInteger('term_min');
            $table->unsignedSmallInteger('term_max');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // min_amount must not exceed max_amount, term_min must not exceed
        // term_max — invariants that belong at the database level because
        // they must hold no matter which code path writes the row (including
        // a future admin UI, a seeder, or a raw SQL fix).
        DB::statement('ALTER TABLE loan_products ADD CONSTRAINT loan_products_amount_range_check CHECK (min_amount <= max_amount)');
        DB::statement('ALTER TABLE loan_products ADD CONSTRAINT loan_products_term_range_check CHECK (term_min <= term_max)');
        DB::statement('ALTER TABLE loan_products ADD CONSTRAINT loan_products_positive_amounts_check CHECK (min_amount > 0 AND max_amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
