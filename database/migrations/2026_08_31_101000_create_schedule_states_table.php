<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-016 §2.3 — per-user schedule review state. Reality changes mark the
 * accepted schedule as needing review (bounded window overlap test); explicit
 * apply or a no-change Sync Now clears it. One row per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_states', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->boolean('needs_review')->default(false)->index();
            $table->json('reasons')->nullable();
            $table->dateTime('impacted_at')->nullable();
            $table->unsignedInteger('last_reviewed_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_states');
    }
};
