<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adaptive_context', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('energy_level')->nullable();
            $table->unsignedTinyInteger('stress_level')->nullable();
            $table->unsignedTinyInteger('task_difficulty')->nullable();
            $table->unsignedTinyInteger('skill_familiarity')->nullable();
            $table->unsignedSmallInteger('interruption_count')->nullable();
            $table->unsignedSmallInteger('context_switch_cost')->nullable();
            $table->unsignedInteger('focus_duration_minutes')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['user_id', 'checked_at']);
            $table->index(['user_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adaptive_context');
    }
};
