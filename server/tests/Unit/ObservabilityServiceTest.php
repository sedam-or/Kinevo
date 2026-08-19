<?php

namespace Tests\Unit;

use App\Domain\Observability\Contracts\SchedulerRunRepository;
use App\Domain\Observability\ObservabilityService;
use App\Domain\Observability\SchedulerRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ObservabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function repo(): SchedulerRunRepository
    {
        return app(SchedulerRunRepository::class);
    }

    public function test_database_health_is_reported(): void
    {
        $service = new ObservabilityService($this->repo());

        $this->assertTrue($service->databaseHealthy());
        $this->assertTrue($service->storageWritable());
    }

    public function test_queue_counts_are_reported(): void
    {
        \DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);
        \DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'test',
        ]);

        $service = new ObservabilityService($this->repo());

        $this->assertSame(1, $service->queuePending());
        $this->assertSame(1, $service->queueFailed());
    }

    public function test_snapshot_includes_no_sensitive_fields(): void
    {
        $this->repo()->record(SchedulerRun::success(null, 'eod:reconcile:prompt', 12));

        $service = new ObservabilityService($this->repo());
        $snapshot = $service->snapshot();

        $this->assertTrue($snapshot['database']['healthy']);
        $this->assertArrayHasKey('pending', $snapshot['queue']);
        $this->assertArrayHasKey('failed', $snapshot['queue']);
        $this->assertCount(1, $snapshot['scheduler_runs']);
        $this->assertSame('eod:reconcile:prompt', $snapshot['scheduler_runs'][0]['job']);

        // Never include payloads/notes/prompts.
        $this->assertArrayNotHasKey('payload', $snapshot);
        $this->assertArrayNotHasKey('notes', $snapshot);
    }
}
