<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-P25-001 — cost pricing provenance on ai_runs. `estimated_cost_minor`
 * (added in 2026_08_26_120000) is derived from a versioned price catalog; these
 * columns record where the number came from so historical runs stay
 * reproducible when provider rates change (owner requirement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->string('pricing_source', 20)->default('unpriced')->after('cost_currency');
            $table->string('pricing_snapshot_id', 64)->nullable()->after('pricing_source');
        });
    }

    public function down(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->dropColumn(['pricing_source', 'pricing_snapshot_id']);
        });
    }
};