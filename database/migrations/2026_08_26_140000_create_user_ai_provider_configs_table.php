<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-P25-008 — per-user BYOK provider config: one encrypted credential row
 * per user (unique user_id). API key is encrypted server-side (Laravel Crypt,
 * app key); it is never stored or transmitted in plaintext. Enabled row opts
 * the user out of Kinevo-hosted inference billing for the request path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ai_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('model', 120)->nullable();
            $table->string('base_url', 255)->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ai_provider_configs');
    }
};