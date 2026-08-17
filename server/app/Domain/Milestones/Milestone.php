<?php

namespace App\Domain\Milestones;

use App\Domain\Milestones\ValueObjects\MilestoneStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Milestone aggregate — ordered intermediate outcome proving progress toward a Goal
 * (SRS §7.3, FR-51). Belongs to exactly one Goal; no recursive nesting.
 * Immutable value semantics: state changes return a new instance.
 */
final class Milestone
{
    private const PROGRESS_MODES = ['derived', 'manual'];

    public function __construct(
        public readonly int $id,
        public readonly int $goalId,
        public readonly int $userId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly int $sequence,
        public readonly ?CarbonImmutable $targetDate,
        public readonly ?int $estimatedMinutes,
        public readonly MilestoneStatus $status,
        public readonly string $progressMode,
        public readonly int $progress,
        public readonly ?CarbonImmutable $completedAt,
        public readonly int $version,
    ) {}

    public static function create(
        int $goalId,
        int $userId,
        string $title,
        ?string $description,
        int $sequence,
        ?CarbonImmutable $targetDate,
        ?int $estimatedMinutes,
        string $progressMode = 'derived',
    ): self {
        if (! in_array($progressMode, self::PROGRESS_MODES, true)) {
            throw new InvalidArgumentException("Unsupported progress mode: {$progressMode}");
        }

        return new self(
            0,
            $goalId,
            $userId,
            trim($title),
            $description,
            $sequence,
            $targetDate,
            $estimatedMinutes,
            MilestoneStatus::planned(),
            $progressMode,
            0,
            null,
            1,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->goalId,
            $this->userId,
            $this->title,
            $this->description,
            $this->sequence,
            $this->targetDate,
            $this->estimatedMinutes,
            $this->status,
            $this->progressMode,
            $this->progress,
            $this->completedAt,
            $this->version,
        );
    }

    public function withTitle(string $title): self
    {
        return $this->reborn(['title' => trim($title)]);
    }

    public function withDescription(?string $description): self
    {
        return $this->reborn(['description' => $description]);
    }

    public function withSequence(int $sequence): self
    {
        return $this->reborn(['sequence' => $sequence]);
    }

    public function withTargetDate(?CarbonImmutable $targetDate): self
    {
        return $this->reborn(['targetDate' => $targetDate]);
    }

    public function withEstimatedMinutes(?int $estimatedMinutes): self
    {
        if ($estimatedMinutes !== null && $estimatedMinutes < 0) {
            throw new InvalidArgumentException('Estimated minutes cannot be negative.');
        }

        return $this->reborn(['estimatedMinutes' => $estimatedMinutes]);
    }

    /**
     * Transition to a new status, validating the state machine (MilestoneStatus transitions).
     * Completing sets completed_at; leaving completed clears it.
     */
    public function withStatus(MilestoneStatus $status, ?CarbonImmutable $now = null): self
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                "Invalid milestone status transition: {$this->status->value} → {$status->value}"
            );
        }

        $completedAt = $status->equals(MilestoneStatus::completed())
            ? ($now ?? CarbonImmutable::now())
            : null;

        return new self(
            $this->id,
            $this->goalId,
            $this->userId,
            $this->title,
            $this->description,
            $this->sequence,
            $this->targetDate,
            $this->estimatedMinutes,
            $status,
            $this->progressMode,
            $this->progress,
            $completedAt,
            $this->version + 1,
        );
    }

    /**
     * Update derived/audited progress snapshot (0-100).
     */
    public function withProgress(int $progress): self
    {
        if ($progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Progress must be between 0 and 100.');
        }

        return $this->reborn(['progress' => $progress, 'version' => $this->version + 1]);
    }

    public function isCompleted(): bool
    {
        return $this->status->equals(MilestoneStatus::completed());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'goal_id' => $this->goalId,
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'sequence' => $this->sequence,
            'target_date' => $this->targetDate?->toDateString(),
            'estimated_minutes' => $this->estimatedMinutes,
            'status' => $this->status->value,
            'progress_mode' => $this->progressMode,
            'progress' => $this->progress,
            'completed_at' => $this->completedAt?->toDateTimeString(),
            'version' => $this->version,
        ];
    }

    /**
     * @param  array<string, mixed>  $props  partial changes merged over current state
     */
    private function reborn(array $props): self
    {
        $merged = array_merge([
            'id' => $this->id,
            'goalId' => $this->goalId,
            'userId' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'sequence' => $this->sequence,
            'targetDate' => $this->targetDate,
            'estimatedMinutes' => $this->estimatedMinutes,
            'status' => $this->status,
            'progressMode' => $this->progressMode,
            'progress' => $this->progress,
            'completedAt' => $this->completedAt,
            'version' => $this->version,
        ], $props);

        return new self(
            $merged['id'],
            $merged['goalId'],
            $merged['userId'],
            $merged['title'],
            $merged['description'],
            $merged['sequence'],
            $merged['targetDate'],
            $merged['estimatedMinutes'],
            $merged['status'],
            $merged['progressMode'],
            $merged['progress'],
            $merged['completedAt'],
            $merged['version'],
        );
    }
}
