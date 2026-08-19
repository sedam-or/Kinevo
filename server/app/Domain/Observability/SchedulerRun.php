<?php

namespace App\Domain\Observability;

use Carbon\CarbonImmutable;

/**
 * Scheduler run telemetry record (SRS §7.8, §16.5). Safe metadata only —
 * job name, status, duration, error code; never task/note content.
 */
final class SchedulerRun
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $userId,
        public readonly string $job,
        public readonly string $status,
        public readonly int $durationMs,
        public readonly ?string $error,
        public readonly CarbonImmutable $startedAt,
    ) {}

    public static function success(?int $userId, string $job, int $durationMs, ?CarbonImmutable $startedAt = null): self
    {
        return new self(null, $userId, $job, 'success', $durationMs, null, $startedAt ?? CarbonImmutable::now());
    }

    public static function failed(?int $userId, string $job, int $durationMs, string $error, ?CarbonImmutable $startedAt = null): self
    {
        return new self(null, $userId, $job, 'failed', $durationMs, $error, $startedAt ?? CarbonImmutable::now());
    }

    public function withId(int $id): self
    {
        return new self($id, $this->userId, $this->job, $this->status, $this->durationMs, $this->error, $this->startedAt);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'job' => $this->job,
            'status' => $this->status,
            'duration_ms' => $this->durationMs,
            'error' => $this->error,
            'started_at' => $this->startedAt->toISOString(),
        ];
    }
}
