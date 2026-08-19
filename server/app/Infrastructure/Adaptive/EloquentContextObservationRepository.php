<?php

namespace App\Infrastructure\Adaptive;

use App\Domain\Adaptive\ContextObservation;
use App\Domain\Adaptive\Contracts\ContextObservationRepository;
use App\Domain\Adaptive\ValueObjects\SignalLevel;
use App\Models\AdaptiveContext as AdaptiveContextModel;
use Carbon\CarbonImmutable;

final class EloquentContextObservationRepository implements ContextObservationRepository
{
    public function create(ContextObservation $observation): ContextObservation
    {
        $model = AdaptiveContextModel::query()->create([
            'user_id' => $observation->userId,
            'task_id' => $observation->taskId,
            'energy_level' => $observation->energy?->value,
            'stress_level' => $observation->stress?->value,
            'task_difficulty' => $observation->difficulty?->value,
            'skill_familiarity' => $observation->familiarity?->value,
            'interruption_count' => $observation->interruptionCount,
            'context_switch_cost' => $observation->contextSwitchCost,
            'focus_duration_minutes' => $observation->focusDurationMinutes,
            'checked_at' => $observation->checkedAt,
        ]);

        return $this->toDomain($model);
    }

    public function listForUser(int $userId, int $limit = 50): array
    {
        return AdaptiveContextModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listForTask(int $userId, int $taskId, int $limit = 50): array
    {
        return AdaptiveContextModel::query()
            ->where('user_id', $userId)
            ->where('task_id', $taskId)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listSince(int $userId, CarbonImmutable $since, int $limit = 200): array
    {
        return AdaptiveContextModel::query()
            ->where('user_id', $userId)
            ->where('checked_at', '>=', $since)
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    private function toDomain(AdaptiveContextModel $model): ContextObservation
    {
        return new ContextObservation(
            $model->id,
            $model->user_id,
            $model->task_id,
            $model->energy_level === null ? null : new SignalLevel($model->energy_level),
            $model->stress_level === null ? null : new SignalLevel($model->stress_level),
            $model->task_difficulty === null ? null : new SignalLevel($model->task_difficulty),
            $model->skill_familiarity === null ? null : new SignalLevel($model->skill_familiarity),
            $model->interruption_count,
            $model->context_switch_cost,
            $model->focus_duration_minutes,
            CarbonImmutable::parse($model->checked_at),
        );
    }
}
