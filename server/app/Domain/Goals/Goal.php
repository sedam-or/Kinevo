<?php

namespace App\Domain\Goals;

use App\Domain\Goals\ValueObjects\GoalHorizon;
use App\Domain\Goals\ValueObjects\GoalStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Goal aggregate — intended outcome with explicit horizon/deadline (SRS §7.2, FR-50).
 * Immutable value semantics: state changes return a new instance.
 */
final class Goal
{
    public const MAX_YEARLY_ACTIVE = 5;

    public const MAX_MONTHLY_ACTIVE_PER_MONTH = 7;

    private const PROGRESS_MODES = ['derived', 'manual'];

    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly GoalHorizon $horizon,
        public readonly ?CarbonImmutable $startDate,
        public readonly ?CarbonImmutable $targetDate,
        public readonly ?string $targetMetric,
        public readonly GoalStatus $status,
        public readonly int $priorityTier,
        public readonly string $progressMode,
        public readonly int $progress,
    ) {}

    /**
     * @param  int  $priorityTier  1-3 (SRS FR-13 tiers)
     */
    public static function create(
        int $userId,
        string $title,
        ?string $description,
        GoalHorizon $horizon,
        ?CarbonImmutable $startDate,
        ?CarbonImmutable $targetDate,
        ?string $targetMetric,
        int $priorityTier = 3,
        string $progressMode = 'derived',
    ): self {
        if ($startDate !== null && $targetDate !== null && $targetDate->lt($startDate)) {
            throw new InvalidArgumentException('Target date cannot precede start date.');
        }

        if (! in_array($progressMode, self::PROGRESS_MODES, true)) {
            throw new InvalidArgumentException("Unsupported progress mode: {$progressMode}");
        }

        return new self(
            0,
            $userId,
            trim($title),
            $description,
            $horizon,
            $startDate,
            $targetDate,
            $targetMetric,
            GoalStatus::draft(),
            $priorityTier,
            $progressMode,
            0,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->title,
            $this->description,
            $this->horizon,
            $this->startDate,
            $this->targetDate,
            $this->targetMetric,
            $this->status,
            $this->priorityTier,
            $this->progressMode,
            $this->progress,
        );
    }

    public function withTitle(string $title): self
    {
        return new self(
            $this->id,
            $this->userId,
            trim($title),
            $this->description,
            $this->horizon,
            $this->startDate,
            $this->targetDate,
            $this->targetMetric,
            $this->status,
            $this->priorityTier,
            $this->progressMode,
            $this->progress,
        );
    }

    public function withDescription(?string $description): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $description,
            $this->horizon,
            $this->startDate,
            $this->targetDate,
            $this->targetMetric,
            $this->status,
            $this->priorityTier,
            $this->progressMode,
            $this->progress,
        );
    }

    public function withHorizon(GoalHorizon $horizon): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->description,
            $horizon,
            $this->startDate,
            $this->targetDate,
            $this->targetMetric,
            $this->status,
            $this->priorityTier,
            $this->progressMode,
            $this->progress,
        );
    }

    public function withDates(?CarbonImmutable $startDate, ?CarbonImmutable $targetDate): self
    {
        if ($startDate !== null && $targetDate !== null && $targetDate->lt($startDate)) {
            throw new InvalidArgumentException('Target date cannot precede start date.');
        }

        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->description,
            $this->horizon,
            $startDate,
            $targetDate,
            $this->targetMetric,
            $this->status,
            $this->priorityTier,
            $this->progressMode,
            $this->progress,
        );
    }

    public function withTargetMetric(?string $targetMetric): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->description,
            $this->horizon,
            $this->startDate,
            $this->targetDate,
            $targetMetric,
            $this->status,
            $this->priorityTier,
            $this->progressMode,
            $this->progress,
        );
    }

    public function withPriorityTier(int $priorityTier): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->description,
            $this->horizon,
            $this->startDate,
            $this->targetDate,
            $this->targetMetric,
            $this->status,
            $priorityTier,
            $this->progressMode,
            $this->progress,
        );
    }

    /**
     * Transition to a new status, validating the state machine (GoalStatus transitions).
     */
    public function withStatus(GoalStatus $status): self
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                "Invalid goal status transition: {$this->status->value} → {$status->value}"
            );
        }

        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->description,
            $this->horizon,
            $this->startDate,
            $this->targetDate,
            $this->targetMetric,
            $status,
            $this->priorityTier,
            $this->progressMode,
            $this->progress,
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

        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->description,
            $this->horizon,
            $this->startDate,
            $this->targetDate,
            $this->targetMetric,
            $this->status,
            $this->priorityTier,
            $this->progressMode,
            $progress,
        );
    }

    public function isDeadlineBound(): bool
    {
        return $this->targetDate !== null;
    }

    /**
     * Calendar days remaining until the target date (FR-50). Negative when overdue.
     */
    public function remainingDays(?CarbonImmutable $today = null): ?int
    {
        if ($this->targetDate === null) {
            return null;
        }

        $reference = ($today ?? CarbonImmutable::now())->startOfDay();

        return (int) $reference->diffInDays($this->targetDate->startOfDay(), false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'horizon' => $this->horizon->value,
            'start_date' => $this->startDate?->toDateString(),
            'target_date' => $this->targetDate?->toDateString(),
            'target_metric' => $this->targetMetric,
            'status' => $this->status->value,
            'priority_tier' => $this->priorityTier,
            'progress_mode' => $this->progressMode,
            'progress' => $this->progress,
        ];
    }
}
