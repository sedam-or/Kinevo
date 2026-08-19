<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Entities\AiProposal;
use App\Domain\Ai\ValueObjects\AiProposalType;
use App\Models\AiProposal as AiProposalModel;
use Carbon\CarbonImmutable;

final readonly class EloquentAiProposalRepository implements AiProposalRepository
{
    public function persist(AiProposal $proposal): AiProposal
    {
        $model = AiProposalModel::query()->create([
            'user_id' => $proposal->userId,
            'proposal_type' => $proposal->type->value,
            'schema_version' => $proposal->schemaVersion,
            'payload' => $proposal->payload,
            'validation_result' => 'valid',
            'decision' => $proposal->decision,
            'operation_id' => $proposal->operationId,
        ]);

        return $proposal->withId($model->id);
    }

    public function findForUser(int $userId, int $proposalId): ?AiProposal
    {
        $model = AiProposalModel::query()
            ->where('user_id', $userId)
            ->where('id', $proposalId)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForUser(
        int $userId,
        ?string $proposalType = null,
        ?string $decision = null,
        int $limit = 50,
    ): array {
        $query = AiProposalModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($proposalType !== null) {
            $query->where('proposal_type', $proposalType);
        }

        if ($decision !== null) {
            $query->where('decision', $decision);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function updateDecision(AiProposal $proposal): AiProposal
    {
        AiProposalModel::query()
            ->where('id', $proposal->id)
            ->update([
                'decision' => $proposal->decision,
                'operation_id' => $proposal->operationId,
            ]);

        return $proposal;
    }

    private function toDomain(AiProposalModel $model): AiProposal
    {
        return new AiProposal(
            $model->id,
            $model->user_id,
            new AiProposalType($model->proposal_type),
            $model->schema_version,
            $model->payload ?? [],
            $model->decision,
            $model->operation_id,
            CarbonImmutable::parse($model->created_at),
        );
    }
}
