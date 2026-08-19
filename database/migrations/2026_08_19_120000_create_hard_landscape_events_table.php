<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hard_landscape_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('type', 20);
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('recurrence', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hard_landscape_events');
    }
};
