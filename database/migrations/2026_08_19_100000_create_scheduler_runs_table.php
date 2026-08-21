<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scheduler run audit/telemetry (SRS §7.8, §16.5). Safe metadata only —
        // never task content or notes.
        Schema::create('scheduler_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('job', 60);
            $table->string('status', 20);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_runs');
    }
};
