<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI audit trail (SRS §7.7). Safe metadata only — never prompts or
        // note content, except the context hash (non-reversible) for matching.
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('model', 120);
            $table->string('proposal_type', 40);
            $table->unsignedTinyInteger('schema_version')->nullable();
            $table->string('prompt_template_version', 20)->nullable();
            $table->string('context_hash', 64)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('error_code', 40)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'proposal_type', 'created_at']);
        });

        // Validated structured proposals awaiting a user decision (FR-62).
        Schema::create('ai_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('proposal_type', 40);
            $table->unsignedTinyInteger('schema_version');
            $table->json('payload');
            $table->string('validation_result', 20)->default('valid');
            $table->string('decision', 20)->default('pending');
            $table->string('operation_id', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_proposals');
        Schema::dropIfExists('ai_runs');
    }
};
