<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-P23-005/006 — subscription state + usage counters (allowance vs
 * consumption are separate). Provider-neutral: P24 billing fills provider
 * columns; until then provider='manual' covers self-serve plan selection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan_code', 32)->index();
            $table->string('provider', 32)->default('manual');
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->string('state', 20)->default('active')->index(); // active|past_due|canceled|expired
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamps();
        });

        Schema::create('usage_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64); // e.g. ai_credits
            $table->string('period', 16); // e.g. 2026-08
            $table->unsignedInteger('consumed')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('subscriptions');
    }
};
