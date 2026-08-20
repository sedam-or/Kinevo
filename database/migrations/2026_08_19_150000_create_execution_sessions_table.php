<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Persisted execution timer (TASK-120). The timer state is derived from
        // these persisted timestamps, never from a client-side model (SRS FR-05:
        // refresh/browser close must not lose a started timer).
        Schema::create('execution_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('last_resumed_at')->nullable();
            $table->unsignedInteger('accumulated_seconds')->default(0);
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_sessions');
    }
};