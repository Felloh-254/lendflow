<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The endpoint the key was used against — the same key value
            // reused against a DIFFERENT endpoint is a different logical
            // operation and must not collide (see the unique index below).
            $table->string('endpoint');
            $table->string('request_hash');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->enum('status', ['processing', 'completed'])->default('processing');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            // This is the constraint the whole feature rests on: the
            // atomic INSERT racing against this unique index is what
            // makes duplicate-request detection safe under real
            // concurrency, not a SELECT-then-INSERT check in application
            // code (which has the same race-condition shape as the
            // double-repayment bug documented in docs/concurrency.md).
            $table->unique(['key', 'endpoint', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
