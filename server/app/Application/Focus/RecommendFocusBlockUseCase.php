<?php

namespace App\Application\Focus;

use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Focus\FocusBlockRecommendation;
use App\Domain\Focus\FocusBlockRecommender;
use Carbon\CarbonImmutable;

/**
 * Recommend a focus block duration for a task from recent completion patterns
 * (SRS §12.4, design.md). Prefers task-scoped history, then user-wide history,
 * then the configured baseline. Never a clinical or "optimal" claim.
 */
final readonly class RecommendFocusBlockUseCase
{
    public const WINDOW_DAYS = 30;

    public function __construct(
        private FocusSessionRepository $sessions,
        private FocusBlockRecommender $recommender,
    ) {}

    public function __invoke(int $userId, ?int $taskId = null): FocusBlockRecommendation
    {
        $since = CarbonImmutable::now()->subDays(self::WINDOW_DAYS);
        $allSessions = $this->sessions->listSince($userId, $since);

        $taskSessions = $taskId === null
            ? []
            : array_values(array_filter(
                $allSessions,
                static fn ($session) => $session->taskId === $taskId,
            ));

        return $this->recommender->recommend($taskSessions, $allSessions);
    }
}
