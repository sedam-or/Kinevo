<?php

namespace App\Domain\Observability;

use App\Domain\Ai\ValueObjects\AiProviderStatus;
use App\Domain\Observability\Contracts\SchedulerRunRepository;
use Illuminate\Support\Facades\DB;

/**
 * Collects safe operational telemetry (SRS §16.5 minimum telemetry).
 *
 * Never includes sensitive content — only counts, health flags, and safe
 * metadata. Sources: live DB health, queue backlog/failures, storage disk
 * availability, AI provider status, and recent scheduler runs.
 */
final readonly class ObservabilityService
{
    public function __construct(
        private SchedulerRunRepository $schedulerRuns,
        private ?\Closure $aiStatus = null,
    ) {}

    public function databaseHealthy(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function queuePending(): int
    {
        try {
            return (int) DB::table('jobs')->count();
        } catch (\Throwable) {
            return -1;
        }
    }

    public function queueFailed(): int
    {
        try {
            return (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return -1;
        }
    }

    public function storageWritable(): bool
    {
        $path = storage_path('framework/cache/data');

        try {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }

            return is_writable($path);
        } catch (\Throwable) {
            return false;
        }
    }

    public function aiStatus(): ?AiProviderStatus
    {
        if ($this->aiStatus === null) {
            return null;
        }

        try {
            return ($this->aiStatus)();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, SchedulerRun>
     */
    public function recentSchedulerRuns(int $limit = 10): array
    {
        try {
            return $this->schedulerRuns->listRecent($limit);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Aggregate SRS §16.5 telemetry into a safe snapshot (no sensitive content).
     *
     * @return array<string, mixed>
     */
    public function snapshot(int $schedulerRunLimit = 10): array
    {
        $ai = $this->aiStatus();

        return [
            'database' => ['healthy' => $this->databaseHealthy()],
            'queue' => [
                'pending' => $this->queuePending(),
                'failed' => $this->queueFailed(),
            ],
            'storage' => ['writable' => $this->storageWritable()],
            'ai_provider' => $ai?->toArray(),
            'scheduler_runs' => array_map(
                static fn (SchedulerRun $run) => $run->toArray(),
                $this->recentSchedulerRuns($schedulerRunLimit),
            ),
            'collected_at' => now()->toISOString(),
        ];
    }
}
