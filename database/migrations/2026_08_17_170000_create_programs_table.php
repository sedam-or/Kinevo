<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('workload_type', 20);
            $table->unsignedInteger('weekly_target_minutes')->nullable();
            $table->unsignedInteger('min_weekly_minutes')->nullable();
            $table->unsignedInteger('max_weekly_minutes')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedTinyInteger('priority_tier')->default(3);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
