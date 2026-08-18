<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id');
            $table->string('link_type', 30);
            $table->timestamps();

            $table->index(['user_id', 'source_type', 'source_id']);
            $table->index(['user_id', 'target_type', 'target_id']);
            $table->unique(
                ['user_id', 'source_type', 'source_id', 'target_type', 'target_id', 'link_type'],
                'knowledge_links_source_target_link_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_links');
    }
};
