<?php

namespace App\Domain\Recharge;

use App\Domain\Recharge\ValueObjects\RechargeStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A persisted recharge timer session (FR-05). Mirrors the execution timer: the
 * timer is a server-side state machine whose elapsed time is always computed
 * from persisted timestamps (refresh/browser close must not lose a started
 * timer). Immutable value semantics: transitions return a new instance.
 */
final class RechargeSession
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly RechargeStatus $status,
        public readonly CarbonImmutable $startedAt,
        public readonly ?CarbonImmutable $lastResumedAt,
        public readonly int $accumulatedSeconds,
        public readonly ?int $durationMinutes,
        public readonly ?CarbonImmutable $endedAt,
    ) {}

    public static function start(int $userId, CarbonImmutable $now): self
    {
        return new self(
            null,
            $userId,
            RechargeStatus::running(),
            $now,
            $now,
            0,
            null,
            null,
        );
    }

    /**
     * Pause a running timer, banking elapsed seconds since the last resume.
     */
    public function pause(CarbonImmutable $now): self
    {
        $this->assertTransition(RechargeStatus::paused());
        $this->assertRunningForAccumulation();

        $elapsed = (int) $this->lastResumedAt->diffInSeconds($now);
        $this->assertClockForward($elapsed);

        return $this->reborn([
            'status' => RechargeStatus::paused(),
            'lastResumedAt' => null,
            'accumulatedSeconds' => $this->accumulatedSeconds + $elapsed,
        ]);
    }

    /**
     * Resume a paused timer, restarting the current running segment.
     */
    public function resume(CarbonImmutable $now): self
    {
        $this->assertTransition(RechargeStatus::running());

        return $this->reborn([
            'status' => RechargeStatus::running(),
            'lastResumedAt' => $now,
        ]);
    }

    /**
     * Complete the timer. The banked/current elapsed seconds become the
     * recorded recharge duration, rounded to at least one minute (FR-05: the
     * recorded duration is the tracked duration, not the nominal 15 minutes).
     */
    public function complete(CarbonImmutable $now): self
    {
        $this->assertTransition(RechargeStatus::completed());

        $accumulated = $this->accumulateUpTo($now);

        return $this->reborn([
            'status' => RechargeStatus::completed(),
            'lastResumedAt' => null,
            'accumulatedSeconds' => $accumulated,
            'durationMinutes' => max(1, (int) round($accumulated / 60)),
            'endedAt' => $now,
        ]);
    }

    /**
     * Abandon the timer. No recharge duration is recorded; elapsed time is
     * kept for audit only.
     */
    public function abandon(CarbonImmutable $now): self
    {
        $this->assertTransition(RechargeStatus::abandoned());

        $accumulated = $this->accumulateUpTo($now);

        return $this->reborn([
            'status' => RechargeStatus::abandoned(),
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
        if (! $this->status->equals(RechargeStatus::running())) {
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
            'status' => $this->status->value,
            'started_at' => $this->startedAt->toISOString(),
            'last_resumed_at' => $this->lastResumedAt?->toISOString(),
            'accumulated_seconds' => $this->accumulatedSeconds,
            'elapsed_seconds' => $this->elapsedSeconds($now),
            'duration_minutes' => $this->durationMinutes,
            'ended_at' => $this->endedAt?->toISOString(),
        ];
    }

    public function withId(int $id): self
    {
        return $this->reborn(['id' => $id]);
    }

    private function assertTransition(RechargeStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new InvalidArgumentException(
                "Invalid recharge status transition: {$this->status->value} → {$next->value}"
            );
        }
    }

    private function assertRunningForAccumulation(): void
    {
        if ($this->lastResumedAt === null) {
            throw new InvalidArgumentException('A running recharge session must have a resume timestamp.');
        }
    }

    private function assertClockForward(int $elapsed): void
    {
        if ($elapsed < 0) {
            throw new InvalidArgumentException('Recharge clock must not move backwards.');
        }
    }

    private function accumulateUpTo(CarbonImmutable $now): int
    {
        if ($this->status->equals(RechargeStatus::running())) {
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
            'status' => $this->status,
            'startedAt' => $this->startedAt,
            'lastResumedAt' => $this->lastResumedAt,
            'accumulatedSeconds' => $this->accumulatedSeconds,
            'durationMinutes' => $this->durationMinutes,
            'endedAt' => $this->endedAt,
        ], $props);

        return new self(
            $merged['id'],
            $merged['userId'],
            $merged['status'],
            $merged['startedAt'],
            $merged['lastResumedAt'],
            $merged['accumulatedSeconds'],
            $merged['durationMinutes'],
            $merged['endedAt'],
        );
    }
}
