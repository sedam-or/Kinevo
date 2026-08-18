<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            $table->string('title', 200)->nullable();
            $table->timestamp('event_at');
            $table->string('operation_id', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_at']);
            $table->unique(['user_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};