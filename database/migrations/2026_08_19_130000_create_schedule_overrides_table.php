<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hard_landscape_event_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to');
            $table->timestamp('override_start_at');
            $table->timestamp('override_end_at');
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'hard_landscape_event_id']);
            $table->index(['user_id', 'effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_overrides');
    }
};
