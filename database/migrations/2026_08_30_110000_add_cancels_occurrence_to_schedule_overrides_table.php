<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-015 (ES-IMPL-05): occurrence cancellation for one-time exceptions.
 * Additive nullable-safe column — existing rows interpret identically
 * (default false = replace/move semantics, unchanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_overrides', function (Blueprint $table): void {
            $table->boolean('cancels_occurrence')->default(false)->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_overrides', function (Blueprint $table): void {
            $table->dropColumn('cancels_occurrence');
        });
    }
};
