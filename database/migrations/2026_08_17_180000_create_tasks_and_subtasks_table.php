<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('backlog');
            $table->unsignedTinyInteger('priority_tier')->default(3);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('progress_mode', 20)->default('derived');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'status', 'due_at']);
            $table->index(['user_id', 'program_id', 'status']);
        });

        Schema::create('subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['task_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtasks');
        Schema::dropIfExists('tasks');
    }
};