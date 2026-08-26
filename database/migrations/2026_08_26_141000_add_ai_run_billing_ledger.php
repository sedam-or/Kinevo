<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-P25-008 — billing ledger separation on ai_runs. `kinevo` = the request
 * ran on a Kinevo-funded provider (spends ai_credits, estimated cost is
 * Kinevo-borne); `byok` = the user's own credential was used (no credit spent,
 * no Kinevo cost). This enables the Usage → Estimated Cost → Back
 * `billable_to_kinevo=false` analytics seam (owner requirement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->string('billing_ledger', 10)->default('kinevo')->after('pricing_snapshot_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->dropColumn('billing_ledger');
        });
    }
};