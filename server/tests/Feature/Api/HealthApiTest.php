<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_is_public_and_reports_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database.healthy', true);
    }

    public function test_metrics_requires_authentication(): void
    {
        $this->getJson('/api/v1/metrics')->assertStatus(401);
        $this->getJson('/api/v1/observability/runs')->assertStatus(401);
    }

    public function test_metrics_returns_telemetry_snapshot(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/metrics')
            ->assertStatus(200)
            ->assertJsonPath('metrics.database.healthy', true)
            ->assertJsonPath('metrics.storage.writable', true);

        $metrics = $response->json('metrics');

        $this->assertArrayHasKey('queue', $metrics);
        $this->assertArrayHasKey('pending', $metrics['queue']);
        $this->assertArrayHasKey('failed', $metrics['queue']);
        $this->assertArrayHasKey('scheduler_runs', $metrics);
    }

    public function test_scheduler_runs_are_recorded_and_listed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        // Run the EOD command (records a scheduler run; no users with tasks → no-op).
        $this->artisan('eod:reconcile --phase=prompt')->assertExitCode(0);

        $this->withToken($token)->getJson('/api/v1/observability/runs')
            ->assertStatus(200)
            ->assertJsonCount(1, 'runs')
            ->assertJsonPath('runs.0.job', 'eod:reconcile:prompt')
            ->assertJsonPath('runs.0.status', 'success');
    }

    public function test_runs_validates_limit(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/observability/runs?limit=0')
            ->assertStatus(422);
    }
}
