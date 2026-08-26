<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-P25-001/002 — AI usage records: per-run request identity (correlatable
 * across logs) and metered consumption (credits + optional provider cost
 * estimate). Cost columns stay NULL until a pricing model is configured
 * (BYOK/pricing is a product decision — TASK P25-008).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->string('request_id', 64)->nullable()->after('id');
            $table->unsignedInteger('credits_consumed')->default(0)->after('output_tokens');
            $table->unsignedInteger('estimated_cost_minor')->nullable()->after('credits_consumed');
            $table->string('cost_currency', 8)->nullable()->after('estimated_cost_minor');
        });
    }

    public function down(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->dropColumn(['request_id', 'credits_consumed', 'estimated_cost_minor', 'cost_currency']);
        });
    }
};