<?php

namespace App\Domain\Adaptive;

use App\Domain\Adaptive\ValueObjects\SignalLevel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A user-entered adaptive context check-in (FR-58, SRS §7.6 / domain-model
 * Context Observation). All signals are optional but at least one MUST be
 * present. Signals are subjective/contextual — never clinical measurements.
 */
final class ContextObservation
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly ?int $taskId,
        public readonly ?SignalLevel $energy,
        public readonly ?SignalLevel $stress,
        public readonly ?SignalLevel $difficulty,
        public readonly ?SignalLevel $familiarity,
        public readonly ?int $interruptionCount,
        public readonly ?int $contextSwitchCost,
        public readonly ?int $focusDurationMinutes,
        public readonly CarbonImmutable $checkedAt,
    ) {}

    public static function create(
        int $userId,
        ?int $taskId = null,
        ?SignalLevel $energy = null,
        ?SignalLevel $stress = null,
        ?SignalLevel $difficulty = null,
        ?SignalLevel $familiarity = null,
        ?int $interruptionCount = null,
        ?int $contextSwitchCost = null,
        ?int $focusDurationMinutes = null,
        ?CarbonImmutable $checkedAt = null,
    ): self {
        $signalCount = count(array_filter([
            $energy, $stress, $difficulty, $familiarity,
            $interruptionCount, $contextSwitchCost, $focusDurationMinutes,
        ], static fn ($signal) => $signal !== null));

        if ($signalCount === 0) {
            throw new InvalidArgumentException('A context check-in requires at least one signal.');
        }

        foreach ([$interruptionCount, $contextSwitchCost, $focusDurationMinutes] as $count) {
            if ($count !== null && $count < 0) {
                throw new InvalidArgumentException('Counts and durations cannot be negative.');
            }
        }

        return new self(
            null,
            $userId,
            $taskId,
            $energy,
            $stress,
            $difficulty,
            $familiarity,
            $interruptionCount,
            $contextSwitchCost,
            $focusDurationMinutes,
            $checkedAt ?? CarbonImmutable::now(),
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->taskId,
            $this->energy,
            $this->stress,
            $this->difficulty,
            $this->familiarity,
            $this->interruptionCount,
            $this->contextSwitchCost,
            $this->focusDurationMinutes,
            $this->checkedAt,
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
            'energy_level' => $this->energy?->value,
            'stress_level' => $this->stress?->value,
            'task_difficulty' => $this->difficulty?->value,
            'skill_familiarity' => $this->familiarity?->value,
            'interruption_count' => $this->interruptionCount,
            'context_switch_cost' => $this->contextSwitchCost,
            'focus_duration_minutes' => $this->focusDurationMinutes,
            'checked_at' => $this->checkedAt->toISOString(),
        ];
    }
}
