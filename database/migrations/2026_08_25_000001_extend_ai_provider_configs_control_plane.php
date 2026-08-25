<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-P18-001/P18-002 — extend ai_provider_configs into the control-plane
 * shape: explicit protocol capability, stored (safe) credential hint so the
 * settings read never decrypts, and last-verification metadata.
 *
 * user_id is nullable on the single-owner MVP seam and becomes the owner
 * scope key when multi-context identities arrive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_provider_configs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
            $table->string('protocol', 32)->nullable()->after('provider');
            $table->string('credential_hint', 64)->nullable()->after('api_key');
            $table->timestamp('last_verified_at')->nullable()->after('enabled');
            $table->string('last_status', 32)->nullable()->after('last_verified_at');
            $table->string('last_error_code', 64)->nullable()->after('last_status');
        });
    }

    public function down(): void
    {
        Schema::table('ai_provider_configs', function (Blueprint $table) {
            $table->dropColumn([
                'user_id',
                'protocol',
                'credential_hint',
                'last_verified_at',
                'last_status',
                'last_error_code',
            ]);
        });
    }
};
