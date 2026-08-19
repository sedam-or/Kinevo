<?php

namespace App\Application\Observability;

use App\Domain\Observability\Contracts\SchedulerRunRepository;
use App\Domain\Observability\SchedulerRun;

/**
 * Records a scheduler run for telemetry (SRS §7.8, §16.5). Safe metadata only.
 */
final readonly class RecordSchedulerRunUseCase
{
    public function __construct(
        private SchedulerRunRepository $runs,
    ) {}

    public function success(?int $userId, string $job, int $durationMs): SchedulerRun
    {
        return $this->runs->record(SchedulerRun::success($userId, $job, $durationMs));
    }

    public function failed(?int $userId, string $job, int $durationMs, string $error): SchedulerRun
    {
        return $this->runs->record(SchedulerRun::failed($userId, $job, $durationMs, $error));
    }
}
