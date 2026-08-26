<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** TASK-P22-002/P22-005/P22-006 — brute-force defense + abuse-limit classes. */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_five_attempts_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'nobody@example.test', 'password' => 'wrong-pass',
            ]);
        }
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.test', 'password' => 'wrong-pass',
        ])->assertStatus(429);
    }

    public function test_ai_generate_is_throttled_per_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        config(['ai.driver' => 'mock']);

        RateLimiter::clear(($user->id).'|ai');
        for ($i = 0; $i < 10; $i++) {
            $this->withToken($token)->postJson('/api/v1/ai/generate', [
                'role' => 'natural_language_explanation', 'prompt' => "ping {$i}",
            ])->assertStatus(200);
        }

        $this->withToken($token)->postJson('/api/v1/ai/generate', [
            'role' => 'natural_language_explanation', 'prompt' => 'over limit',
        ])->assertStatus(429);
    }

    public function test_token_expiration_config_is_enforced_value(): void
    {
        // TASK-P22-002 — 30-day absolute lifetime (minutes).
        $this->assertSame(60 * 24 * 30, (int) config('sanctum.expiration'));
    }
}
