<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Ai\Entities\AiRun;
use App\Models\AiRun as AiRunModel;
use Carbon\CarbonImmutable;

final readonly class EloquentAiRunRepository implements AiRunRepository
{
    public function record(AiRun $run): AiRun
    {
        $model = AiRunModel::query()->create([
            'request_id' => $run->requestId,
            'user_id' => $run->userId,
            'provider' => $run->provider,
            'model' => $run->model,
            'proposal_type' => $run->proposalType,
            'schema_version' => $run->schemaVersion,
            'prompt_template_version' => $run->promptTemplateVersion,
            'context_hash' => $run->contextHash,
            'input_tokens' => $run->inputTokens,
            'output_tokens' => $run->outputTokens,
            'credits_consumed' => $run->creditsConsumed,
            'estimated_cost_minor' => $run->estimatedCostMinor,
            'cost_currency' => $run->costCurrency,
            'pricing_source' => $run->pricingSource,
            'pricing_snapshot_id' => $run->pricingSnapshotId,
            'status' => $run->status,
            'latency_ms' => $run->latencyMs,
            'error_code' => $run->errorCode,
        ]);

        return $run->withId($model->id);
    }

    public function listForUser(
        int $userId,
        ?string $proposalType = null,
        int $limit = 50,
    ): array {
        $query = AiRunModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($proposalType !== null) {
            $query->where('proposal_type', $proposalType);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    private function toDomain(AiRunModel $model): AiRun
    {
        return new AiRun(
            $model->id,
            $model->user_id,
            $model->provider,
            $model->model,
            $model->proposal_type,
            $model->schema_version,
            $model->prompt_template_version,
            $model->context_hash,
            $model->input_tokens,
            $model->output_tokens,
            $model->status,
            $model->latency_ms,
            $model->error_code,
            CarbonImmutable::parse($model->created_at),
            $model->request_id,
            (int) $model->credits_consumed,
            $model->estimated_cost_minor !== null ? (int) $model->estimated_cost_minor : null,
            $model->cost_currency,
            $model->pricing_source,
            $model->pricing_snapshot_id,
        );
    }
}
