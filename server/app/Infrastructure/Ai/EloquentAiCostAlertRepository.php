<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiCostAlertRepository;
use App\Domain\Ai\Entities\AiCostAlert;
use App\Models\AiCostAlert as AiCostAlertModel;
use Carbon\CarbonImmutable;

final readonly class EloquentAiCostAlertRepository implements AiCostAlertRepository
{
    public function create(AiCostAlert $alert): AiCostAlert
    {
        $model = AiCostAlertModel::query()->create([
            'user_id' => $alert->userId,
            'kind' => $alert->kind,
            'threshold' => $alert->threshold,
            'context' => $alert->context === [] ? null : $alert->context,
            'seen_at' => $alert->seenAt,
        ]);

        return $alert->withId($model->id);
    }

    /** @return array<int, AiCostAlert> */
    public function listUnseenForUser(int $userId, int $limit = 20): array
    {
        return AiCostAlertModel::query()
            ->where('user_id', $userId)
            ->where('kind', AiCostAlert::KIND_USER_USAGE_THRESHOLD)
            ->whereNull('seen_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function markAllSeenForUser(int $userId): int
    {
        return (int) AiCostAlertModel::query()
            ->where('user_id', $userId)
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);
    }

    public function existsSince(
        string $kind,
        ?int $userId,
        CarbonImmutable $since,
        ?int $threshold = null,
    ): bool {
        $query = AiCostAlertModel::query()
            ->where('kind', $kind)
            ->where('created_at', '>=', $since);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        if ($threshold !== null) {
            $query->where('threshold', $threshold);
        }

        return $query->exists();
    }

    private function toDomain(AiCostAlertModel $model): AiCostAlert
    {
        return new AiCostAlert(
            $model->id,
            $model->user_id,
            $model->kind,
            $model->threshold !== null ? (int) $model->threshold : null,
            $model->context ?? [],
            $model->seen_at !== null ? CarbonImmutable::parse($model->seen_at) : null,
            CarbonImmutable::parse($model->created_at),
        );
    }
}
