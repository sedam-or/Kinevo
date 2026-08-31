<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-016 §2.10 — Sacred Anchor producer (scheduling slice of SRS FR-04).
 * At most one task per user carries the anchor flag; the draft generator
 * places it first (25 min, first qualifying slot at/after 06:00, locked).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->boolean('is_sacred_anchor')->default(false)->after('progress_mode');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('is_sacred_anchor');
        });
    }
};
