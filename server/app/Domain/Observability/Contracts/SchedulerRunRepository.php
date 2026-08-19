<?php

namespace App\Domain\Observability\Contracts;

use App\Domain\Observability\SchedulerRun;

interface SchedulerRunRepository
{
    public function record(SchedulerRun $run): SchedulerRun;

    /**
     * @return array<int, SchedulerRun>
     */
    public function listRecent(int $limit = 20): array;
}
