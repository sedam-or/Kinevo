<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-017 §2.4 — server operation ledger. One row per client operation_id
 * (unique per user) records the at-most-once outcome. Stores a payload hash
 * and a bounded result (entity_id, version, code) — never full note/task
 * content (privacy, §2.20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_operations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('operation_id', 64);
            $table->string('operation_type', 40);
            $table->string('entity_type', 40)->default('');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('payload_hash', 64);
            $table->string('status', 20)->default('applied');
            $table->json('result')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            // ADR-017 §2.3 — (user_id, operation_id) is the at-most-once guard.
            $table->unique(['user_id', 'operation_id'], 'offline_operations_idempotency');
            $table->index(['user_id', 'created_at'], 'offline_operations_retention');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_operations');
    }
};