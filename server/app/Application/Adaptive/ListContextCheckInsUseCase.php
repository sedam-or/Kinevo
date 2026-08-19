<?php

namespace App\Application\Adaptive;

use App\Domain\Adaptive\ContextObservation;
use App\Domain\Adaptive\Contracts\ContextObservationRepository;

/**
 * List a user's recent context check-ins, newest first (FR-58).
 */
final readonly class ListContextCheckInsUseCase
{
    public function __construct(
        private ContextObservationRepository $observations,
    ) {}

    /**
     * @return array<int, ContextObservation>
     */
    public function __invoke(int $userId, int $limit = 50): array
    {
        return $this->observations->listForUser($userId, $limit);
    }
}
