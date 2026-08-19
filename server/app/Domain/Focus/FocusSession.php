<?php

namespace App\Domain\Focus;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * An actual focus execution interval (SRS §7 `focus_sessions`, §12.2
 * focus-session completion signal). Records what really happened; used as the
 * "recent completion patterns" basis for focus block recommendations.
 */
final class FocusSession
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly ?int $taskId,
        public readonly CarbonImmutable $startedAt,
        public readonly CarbonImmutable $endedAt,
        public readonly int $durationMinutes,
    ) {}

    public static function create(
        int $userId,
        CarbonImmutable $startedAt,
        CarbonImmutable $endedAt,
        ?int $taskId = null,
    ): self {
        if (! $endedAt->greaterThan($startedAt)) {
            throw new InvalidArgumentException('A focus session must end after it starts.');
        }

        $duration = (int) floor($startedAt->diffInMinutes($endedAt));
        if ($duration < 1) {
            throw new InvalidArgumentException('A focus session must last at least one minute.');
        }

        return new self(
            null,
            $userId,
            $taskId,
            $startedAt,
            $endedAt,
            $duration,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->taskId,
            $this->startedAt,
            $this->endedAt,
            $this->durationMinutes,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'task_id' => $this->taskId,
            'started_at' => $this->startedAt->toISOString(),
            'ended_at' => $this->endedAt->toISOString(),
            'duration_minutes' => $this->durationMinutes,
        ];
    }
}
