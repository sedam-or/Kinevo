<?php

namespace App\Domain\Execution;

use App\Domain\Execution\ValueObjects\ExecutionStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A persisted execution timer session (TASK-120). The timer is a server-side
 * state machine whose elapsed time is always computed from persisted
 * timestamps (SRS FR-05 exception flow: refresh/browser close must not lose a
 * started timer). Immutable value semantics: transitions return a new instance.
 */
final class ExecutionSession
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly int $taskId,
        public readonly ExecutionStatus $status,
        public readonly CarbonImmutable $startedAt,
        public readonly ?CarbonImmutable $lastResumedAt,
        public readonly int $accumulatedSeconds,
        public readonly ?CarbonImmutable $endedAt,
    ) {}

    public static function start(int $userId, int $taskId, CarbonImmutable $now): self
    {
        return new self(
            null,
            $userId,
            $taskId,
            ExecutionStatus::running(),
            $now,
            $now,
            0,
            null,
        );
    }

    /**
     * Pause a running timer, banking elapsed seconds since the last resume.
     */
    public function pause(CarbonImmutable $now): self
    {
        $this->assertTransition(ExecutionStatus::paused());
        $this->assertRunningForAccumulation();

        $elapsed = (int) $this->lastResumedAt->diffInSeconds($now);
        $this->assertClockForward($elapsed);

        return $this->reborn([
            'status' => ExecutionStatus::paused(),
            'lastResumedAt' => null,
            'accumulatedSeconds' => $this->accumulatedSeconds + $elapsed,
        ]);
    }

    /**
     * Resume a paused timer, restarting the current running segment.
     */
    public function resume(CarbonImmutable $now): self
    {
        $this->assertTransition(ExecutionStatus::running());

        return $this->reborn([
            'status' => ExecutionStatus::running(),
            'lastResumedAt' => $now,
        ]);
    }

    /**
     * Complete the timer. The banked/current elapsed seconds become the focus
     * session duration (FR-05: recorded duration is the tracked duration).
     */
    public function complete(CarbonImmutable $now): self
    {
        $this->assertTransition(ExecutionStatus::completed());

        $accumulated = $this->accumulateUpTo($now);

        return $this->reborn([
            'status' => ExecutionStatus::completed(),
            'lastResumedAt' => null,
            'accumulatedSeconds' => $accumulated,
            'endedAt' => $now,
        ]);
    }

    /**
     * Abandon the timer. No focus session is recorded; elapsed time is kept
     * for audit only.
     */
    public function abandon(CarbonImmutable $now): self
    {
        $this->assertTransition(ExecutionStatus::abandoned());

        $accumulated = $this->accumulateUpTo($now);

        return $this->reborn([
            'status' => ExecutionStatus::abandoned(),
            'lastResumedAt' => null,
            'accumulatedSeconds' => $accumulated,
            'endedAt' => $now,
        ]);
    }

    /**
     * Tracked elapsed seconds derived from persisted timestamps (FR-05).
     */
    public function elapsedSeconds(CarbonImmutable $now): int
    {
        if (! $this->status->equals(ExecutionStatus::running())) {
            return $this->accumulatedSeconds;
        }

        $elapsed = (int) $this->lastResumedAt->diffInSeconds($now);

        return $this->accumulatedSeconds + max(0, $elapsed);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(CarbonImmutable $now): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'task_id' => $this->taskId,
            'status' => $this->status->value,
            'started_at' => $this->startedAt->toISOString(),
            'last_resumed_at' => $this->lastResumedAt?->toISOString(),
            'accumulated_seconds' => $this->accumulatedSeconds,
            'elapsed_seconds' => $this->elapsedSeconds($now),
            'ended_at' => $this->endedAt?->toISOString(),
        ];
    }

    public function withId(int $id): self
    {
        return $this->reborn(['id' => $id]);
    }

    private function assertTransition(ExecutionStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new InvalidArgumentException(
                "Invalid execution status transition: {$this->status->value} → {$next->value}"
            );
        }
    }

    private function assertRunningForAccumulation(): void
    {
        if ($this->lastResumedAt === null) {
            throw new InvalidArgumentException('A running execution session must have a resume timestamp.');
        }
    }

    private function assertClockForward(int $elapsed): void
    {
        if ($elapsed < 0) {
            throw new InvalidArgumentException('Execution clock must not move backwards.');
        }
    }

    private function accumulateUpTo(CarbonImmutable $now): int
    {
        if ($this->status->equals(ExecutionStatus::running())) {
            $this->assertRunningForAccumulation();
            $elapsed = (int) $this->lastResumedAt->diffInSeconds($now);
            $this->assertClockForward($elapsed);

            return $this->accumulatedSeconds + $elapsed;
        }

        return $this->accumulatedSeconds;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function reborn(array $props): self
    {
        $merged = array_merge([
            'id' => $this->id,
            'userId' => $this->userId,
            'taskId' => $this->taskId,
            'status' => $this->status,
            'startedAt' => $this->startedAt,
            'lastResumedAt' => $this->lastResumedAt,
            'accumulatedSeconds' => $this->accumulatedSeconds,
            'endedAt' => $this->endedAt,
        ], $props);

        return new self(
            $merged['id'],
            $merged['userId'],
            $merged['taskId'],
            $merged['status'],
            $merged['startedAt'],
            $merged['lastResumedAt'],
            $merged['accumulatedSeconds'],
            $merged['endedAt'],
        );
    }
}
