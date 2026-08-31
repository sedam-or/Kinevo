<?php

namespace App\Infrastructure\OfflineSync;

use App\Domain\OfflineSync\Contracts\OfflineOperationRepository;
use App\Domain\OfflineSync\OfflineOperationRecord;
use App\Models\OfflineOperationModel;
use Carbon\CarbonImmutable;

final readonly class EloquentOfflineOperationRepository implements OfflineOperationRepository
{
    public function find(int $userId, string $operationId): ?OfflineOperationRecord
    {
        $model = OfflineOperationModel::query()
            ->where('user_id', $userId)
            ->where('operation_id', $operationId)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function record(
        int $userId,
        string $operationId,
        string $operationType,
        string $entityType,
        ?int $entityId,
        string $payloadHash,
        string $status,
        ?array $result,
    ): OfflineOperationRecord {
        $now = CarbonImmutable::now();

        $model = OfflineOperationModel::query()->create([
            'user_id' => $userId,
            'operation_id' => $operationId,
            'operation_type' => $operationType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload_hash' => $payloadHash,
            'status' => $status,
            'result' => $result,
            'created_at' => $now,
            'processed_at' => $now,
        ]);

        return $this->toDomain($model);
    }

    public function pruneOlderThan(CarbonImmutable $before): int
    {
        return OfflineOperationModel::query()
            ->where('created_at', '<', $before)
            ->delete();
    }

    private function toDomain(OfflineOperationModel $model): OfflineOperationRecord
    {
        return new OfflineOperationRecord(
            $model->id,
            $model->user_id,
            $model->operation_id,
            $model->operation_type,
            $model->entity_type,
            $model->entity_id,
            $model->payload_hash,
            $model->status,
            $model->result,
            $model->processed_at !== null ? CarbonImmutable::instance($model->processed_at) : null,
            CarbonImmutable::instance($model->created_at),
        );
    }
}
