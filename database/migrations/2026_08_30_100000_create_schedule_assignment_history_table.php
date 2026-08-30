<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-015 (ES-IMPL-06A): immutable history for superseded/deleted schedule
 * assignments. One row per archived placement, written in the SAME
 * transaction as the live mutation (a failed apply leaves neither partial
 * history nor partial live state).
 *
 * `assignment_id` / `task_id` are plain indexed integers (no FK constraints):
 * the live rows are deleted when archived, and task deletion must never wipe
 * a task's placement timeline. `user_id` cascades (account deletion wipes
 * history per the deletion map).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_assignment_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('task_id');
            $table->date('date');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->integer('duration_minutes');
            $table->string('status', 50);
            $table->string('source', 50);
            $table->integer('schedule_version');
            $table->boolean('locked');
            $table->integer('version');
            $table->integer('superseded_by_schedule_version')->nullable();
            $table->string('superseded_by', 50)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
            $table->index(['user_id', 'task_id']);
            $table->index(['user_id', 'schedule_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_assignment_history');
    }
};
