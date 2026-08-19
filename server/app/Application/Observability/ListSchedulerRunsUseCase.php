<?php

namespace App\Application\Observability;

use App\Domain\Observability\Contracts\SchedulerRunRepository;
use App\Domain\Observability\SchedulerRun;

/**
 * Recent scheduler runs for telemetry (SRS §7.8, §16.5). Safe metadata only.
 */
final readonly class ListSchedulerRunsUseCase
{
    public function __construct(
        private SchedulerRunRepository $runs,
    ) {}

    /**
     * @return array<int, SchedulerRun>
     */
    public function __invoke(int $limit = 20): array
    {
        return $this->runs->listRecent($limit);
    }
}
