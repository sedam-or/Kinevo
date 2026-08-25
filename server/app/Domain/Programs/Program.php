<?php

namespace App\Domain\Programs;

use App\Domain\Programs\ValueObjects\ProgramStatus;
use App\Domain\Programs\ValueObjects\ProgramWorkloadType;
use InvalidArgumentException;

/**
 * Program aggregate — sustained workstream (FR-22, FR-26, domain-model Program).
 * Immutable value semantics: state changes return a new instance.
 */
final class Program
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $category,
        public readonly ProgramWorkloadType $workloadType,
        public readonly ?int $weeklyTargetMinutes,
        public readonly ?int $minWeeklyMinutes,
        public readonly ?int $maxWeeklyMinutes,
        public readonly ProgramStatus $status,
        public readonly int $priorityTier,
        public readonly int $version,
        public readonly ?int $workspaceId = null,
    ) {}

    public function withWorkspace(int $workspaceId): self
    {
        return new self(
            $this->id, $this->userId, $this->name, $this->description, $this->category,
            $this->workloadType, $this->weeklyTargetMinutes, $this->minWeeklyMinutes,
            $this->maxWeeklyMinutes, $this->status, $this->priorityTier, $this->version,
            $workspaceId,
        );
    }

    /**
     * FR-26: valid name/category required; Structured uses weekly_target_minutes,
     * Range uses min/max weekly minutes, Flexible uses no weekly capacity.
     */
    public static function create(
        int $userId,
        string $name,
        ?string $description,
        ?string $category,
        ProgramWorkloadType $workloadType,
        ?int $weeklyTargetMinutes = null,
        ?int $minWeeklyMinutes = null,
        ?int $maxWeeklyMinutes = null,
        int $priorityTier = 3,
    ): self {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Program name is required.');
        }

        self::validateWorkload($workloadType, $weeklyTargetMinutes, $minWeeklyMinutes, $maxWeeklyMinutes);

        return new self(
            0,
            $userId,
            trim($name),
            $description,
            $category,
            $workloadType,
            $weeklyTargetMinutes,
            $minWeeklyMinutes,
            $maxWeeklyMinutes,
            ProgramStatus::active(),
            $priorityTier,
            1,
        );
    }

    public function withId(int $id): self
    {
        return $this->reborn(['id' => $id]);
    }

    public function withName(string $name): self
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Program name is required.');
        }

        return $this->reborn(['name' => trim($name)]);
    }

    public function withDescription(?string $description): self
    {
        return $this->reborn(['description' => $description]);
    }

    public function withCategory(?string $category): self
    {
        return $this->reborn(['category' => $category]);
    }

    public function withWorkload(
        ProgramWorkloadType $workloadType,
        ?int $weeklyTargetMinutes = null,
        ?int $minWeeklyMinutes = null,
        ?int $maxWeeklyMinutes = null,
    ): self {
        self::validateWorkload($workloadType, $weeklyTargetMinutes, $minWeeklyMinutes, $maxWeeklyMinutes);

        return $this->reborn([
            'workloadType' => $workloadType,
            'weeklyTargetMinutes' => $weeklyTargetMinutes,
            'minWeeklyMinutes' => $minWeeklyMinutes,
            'maxWeeklyMinutes' => $maxWeeklyMinutes,
        ]);
    }

    public function withPriorityTier(int $priorityTier): self
    {
        if ($priorityTier < 1 || $priorityTier > 3) {
            throw new InvalidArgumentException('Priority tier must be between 1 and 3.');
        }

        return $this->reborn(['priorityTier' => $priorityTier]);
    }

    /**
     * FR-22 lifecycle transition. Completed removes recurring future schedule;
     * Dropped retains historical contribution. Undo window handled at controller layer.
     */
    public function withStatus(ProgramStatus $status): self
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                "Invalid program status transition: {$this->status->value} → {$status->value}"
            );
        }

        return $this->reborn(['status' => $status, 'version' => $this->version + 1]);
    }

    /**
     * FR-26: Structured and Range affect weekly capacity; Flexible does not
     * until its tasks are scheduled, then contributes to overload.
     */
    public function affectsWeeklyCapacity(): bool
    {
        return $this->workloadType->affectsWeeklyCapacity();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'workload_type' => $this->workloadType->value,
            'weekly_target_minutes' => $this->weeklyTargetMinutes,
            'min_weekly_minutes' => $this->minWeeklyMinutes,
            'max_weekly_minutes' => $this->maxWeeklyMinutes,
            'status' => $this->status->value,
            'priority_tier' => $this->priorityTier,
            'version' => $this->version,
            'workspace_id' => $this->workspaceId,
        ];
    }

    /**
     * FR-26 workload validation: min > max rejected; Structured requires a target;
     * Range requires min/max; Flexible forbids weekly target.
     */
    private static function validateWorkload(
        ProgramWorkloadType $workloadType,
        ?int $weeklyTargetMinutes = null,
        ?int $minWeeklyMinutes = null,
        ?int $maxWeeklyMinutes = null,
    ): void {
        if ($workloadType->equals(ProgramWorkloadType::structured())) {
            if ($weeklyTargetMinutes === null || $weeklyTargetMinutes <= 0) {
                throw new InvalidArgumentException('Structured programs require a positive weekly target.');
            }

            return;
        }

        if ($workloadType->equals(ProgramWorkloadType::range())) {
            if ($minWeeklyMinutes === null || $maxWeeklyMinutes === null) {
                throw new InvalidArgumentException('Range programs require min and max weekly minutes.');
            }
            if ($minWeeklyMinutes < 0 || $maxWeeklyMinutes < 0) {
                throw new InvalidArgumentException('Weekly minutes cannot be negative.');
            }
            if ($minWeeklyMinutes > $maxWeeklyMinutes) {
                throw new InvalidArgumentException('Min weekly minutes cannot exceed max.');
            }

            return;
        }

        if ($weeklyTargetMinutes !== null || $minWeeklyMinutes !== null || $maxWeeklyMinutes !== null) {
            throw new InvalidArgumentException('Flexible programs have no weekly capacity target.');
        }
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function reborn(array $props): self
    {
        $merged = array_merge([
            'id' => $this->id,
            'userId' => $this->userId,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'workloadType' => $this->workloadType,
            'weeklyTargetMinutes' => $this->weeklyTargetMinutes,
            'minWeeklyMinutes' => $this->minWeeklyMinutes,
            'maxWeeklyMinutes' => $this->maxWeeklyMinutes,
            'status' => $this->status,
            'priorityTier' => $this->priorityTier,
            'version' => $this->version,
        ], $props);

        return new self(
            $merged['id'],
            $merged['userId'],
            $merged['name'],
            $merged['description'],
            $merged['category'],
            $merged['workloadType'],
            $merged['weeklyTargetMinutes'],
            $merged['minWeeklyMinutes'],
            $merged['maxWeeklyMinutes'],
            $merged['status'],
            $merged['priorityTier'],
            $merged['version'],
        );
    }
}
