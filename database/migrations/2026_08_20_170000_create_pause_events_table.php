<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pause_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('week_start');
            $table->date('week_end');
            $table->json('keep_task_ids')->nullable();
            $table->json('moved_task_ids')->nullable();
            $table->json('conflict_task_ids')->nullable();
            $table->unsignedBigInteger('schedule_version');
            $table->timestamps();

            // FR-07: one emergency pause per user/week (idempotent tagging).
            $table->unique(['user_id', 'type', 'week_start']);
            $table->index(['user_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pause_events');
    }
};