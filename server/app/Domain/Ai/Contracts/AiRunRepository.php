<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Entities\AiRun;

interface AiRunRepository
{
    public function record(AiRun $run): AiRun;

    /**
     * @return array<int, AiRun>
     */
    public function listForUser(
        int $userId,
        ?string $proposalType = null,
        int $limit = 50,
    ): array;
}
