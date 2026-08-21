<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            $table->string('title', 200)->nullable();
            $table->timestamp('occurred_at');
            $table->string('operation_id', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            // §12.5: a progress event references the domain change that created
            // it; the operation id is that reference and guarantees idempotency.
            $table->index(['user_id', 'occurred_at']);
            $table->unique(['user_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_events');
    }
};
