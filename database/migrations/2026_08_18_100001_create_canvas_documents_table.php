<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canvas_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvas_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('schema_version')->default(1);
            $table->jsonb('scene_json');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index('canvas_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canvas_documents');
    }
};
