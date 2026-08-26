<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TASK-P24-006..009 — billing persistence with provider-reference uniqueness. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_subscriptions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('plan_code', 32)->index();
            $t->unsignedBigInteger('price_amount_minor');
            $t->string('price_currency', 8)->default('IDR');
            $t->string('provider', 32)->default('midtrans');
            $t->string('operation_id', 64)->unique(); // TASK-P24-011 checkout idempotency
            $t->string('provider_subscription_id')->nullable()->index();
            $t->string('state', 24)->default('pending')->index(); // SubscriptionState
            $t->timestamp('last_event_at')->nullable();
            $t->boolean('uncertain')->default(false);
            $t->timestamps();
        });

        Schema::create('billing_transactions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('billing_subscription_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 32)->default('midtrans');
            $t->string('provider_transaction_id')->unique();
            $t->unsignedBigInteger('amount_minor');
            $t->string('currency', 8)->default('IDR');
            $t->string('status', 24)->index(); // succeeded|failed|refunded
            $t->timestamp('occurred_at')->nullable();
            $t->timestamps();
        });

        Schema::create('billing_events', function (Blueprint $t): void {
            $t->id();
            $t->string('provider', 32)->default('midtrans');
            $t->string('provider_event_id')->index();
            $t->string('event_type', 48);
            $t->string('payload_hash', 64);
            $t->timestamp('received_at');
            $t->timestamp('processed_at')->nullable();
            $t->string('processing_status', 16)->default('processed'); // processed|ignored|failed
            $t->unsignedInteger('processing_attempts')->default(1);
            $t->string('last_error_code')->nullable();

            $t->unique(['provider', 'provider_event_id']); // TASK-P24-014 idempotency
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('billing_transactions');
        Schema::dropIfExists('billing_subscriptions');
    }
};
