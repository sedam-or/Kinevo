<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-P25-010 — AI usage/cost alert events (domain events first, channels
 * later per owner decision). `user_id` NULL rows are OPS-side alerts (logged +
 * stored; never shown to end users). User alerts surface as unread in-app
 * until marked seen; no notification center is built for this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_cost_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 50);
            $table->unsignedInteger('threshold')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'seen_at', 'created_at']);
            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_cost_alerts');
    }
};