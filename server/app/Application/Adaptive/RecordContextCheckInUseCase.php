<?php

namespace App\Application\Adaptive;

use App\Application\Tasks\GetTaskUseCase;
use App\Domain\Adaptive\ContextObservation;
use App\Domain\Adaptive\Contracts\ContextObservationRepository;
use App\Domain\Adaptive\ValueObjects\SignalLevel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Record a user-entered context check-in (FR-58). The optional task reference
 * is validated for ownership; at least one signal is required.
 */
final readonly class RecordContextCheckInUseCase
{
    public function __construct(
        private ContextObservationRepository $observations,
        private GetTaskUseCase $getTask,
    ) {}

    public function __invoke(
        int $userId,
        ?int $taskId = null,
        ?int $energy = null,
        ?int $stress = null,
        ?int $difficulty = null,
        ?int $familiarity = null,
        ?int $interruptionCount = null,
        ?int $contextSwitchCost = null,
        ?int $focusDurationMinutes = null,
        ?CarbonImmutable $checkedAt = null,
    ): ContextObservation {
        if ($taskId !== null) {
            $this->getTask->__invoke($userId, $taskId);
        }

        try {
            $observation = ContextObservation::create(
                $userId,
                $taskId,
                $energy === null ? null : new SignalLevel($energy),
                $stress === null ? null : new SignalLevel($stress),
                $difficulty === null ? null : new SignalLevel($difficulty),
                $familiarity === null ? null : new SignalLevel($familiarity),
                $interruptionCount,
                $contextSwitchCost,
                $focusDurationMinutes,
                $checkedAt,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Task not found.') {
                throw $e;
            }

            throw new InvalidArgumentException("Invalid context check-in: {$e->getMessage()}");
        }

        return $this->observations->create($observation);
    }
}
