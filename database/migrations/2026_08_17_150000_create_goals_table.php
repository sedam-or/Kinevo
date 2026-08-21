<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('horizon', 20);
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->string('target_metric', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedTinyInteger('priority_tier')->default(3);
            $table->string('progress_mode', 20)->default('derived');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
