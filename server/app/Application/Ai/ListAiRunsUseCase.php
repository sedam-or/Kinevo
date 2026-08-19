<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Ai\Entities\AiRun;

/**
 * List the owner's AI run audit records (SRS §7.7 / §17.8-style observability).
 * Safe metadata only.
 */
final readonly class ListAiRunsUseCase
{
    public function __construct(
        private AiRunRepository $runs,
    ) {}

    /**
     * @return array<int, AiRun>
     */
    public function __invoke(int $userId, ?string $proposalType = null, int $limit = 50): array
    {
        return $this->runs->listForUser($userId, $proposalType, $limit);
    }
}
