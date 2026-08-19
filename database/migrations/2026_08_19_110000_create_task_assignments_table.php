<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->integer('duration_minutes');
            $table->string('status', 50);
            $table->string('source', 50);
            $table->integer('schedule_version')->default(1);
            $table->boolean('locked')->default(false);
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'date', 'start_at']);
            $table->index(['user_id', 'start_at', 'end_at']);
            $table->index(['task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
    }
};
