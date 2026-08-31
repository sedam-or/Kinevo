<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-016 §2.5 — persisted weekly planning drafts. Manual drafts remain
 * ephemeral (client-held JSON); only the weekly trigger stores its output so
 * the review can span sessions. The payload is the exact draft JSON the
 * client echoes to the existing apply endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_drafts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('source', 20)->default('weekly');
            $table->string('status', 20)->default('pending')->index();
            $table->json('payload');
            $table->unsignedInteger('base_version');
            $table->date('horizon_from');
            $table->date('horizon_to');
            $table->date('generated_for_week')->nullable();
            $table->timestamps();

            // ADR-016 §2.1 — one pending weekly draft per user per week anchor;
            // duplicate weekly runs are idempotent by construction.
            $table->unique(['user_id', 'source', 'generated_for_week'], 'schedule_drafts_weekly_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_drafts');
    }
};
