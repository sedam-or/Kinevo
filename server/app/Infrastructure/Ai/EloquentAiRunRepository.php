<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Ai\Entities\AiRun;
use App\Models\AiRun as AiRunModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class EloquentAiRunRepository implements AiRunRepository
{
    public function countSince(int $userId, CarbonImmutable $since, ?string $status = null): int
    {
        $query = AiRunModel::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return (int) $query->count();
    }

    public function sumEstimatedCostSince(int $userId, CarbonImmutable $since): int
    {
        return (int) AiRunModel::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->sum('estimated_cost_minor');
    }

    public function sumEstimatedCostForAllSince(CarbonImmutable $since): int
    {
        return (int) AiRunModel::query()
            ->where('created_at', '>=', $since)
            ->sum('estimated_cost_minor');
    }

    /** @return array{ kinevo_count:int, kinevo_cost_minor:int, byok_count:int, total_count:int } */
    public function monthlyUsageForUser(int $userId, CarbonImmutable $since): array
    {
        $byLedger = DB::table('ai_runs')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->selectRaw('billing_ledger, count(*) as count, coalesce(sum(estimated_cost_minor), 0) as cost')
            ->groupBy('billing_ledger')
            ->get()
            ->mapWithKeys(static fn ($row) => [(string) $row->billing_ledger => (array) $row])
            ->all();

        $kinevo = $byLedger['kinevo'] ?? null;
        $byok = $byLedger['byok'] ?? null;

        return [
            'kinevo_count' => (int) ($kinevo['count'] ?? 0),
            'kinevo_cost_minor' => (int) ($kinevo['cost'] ?? 0),
            'byok_count' => (int) ($byok['count'] ?? 0),
            'total_count' => (int) ($kinevo['count'] ?? 0) + (int) ($byok['count'] ?? 0),
        ];
    }

    /** @return array<int, array{ type:string, count:int, kinevo_cost_minor:int }> */
    public function monthlyBreakdown(int $userId, CarbonImmutable $since): array
    {
        return DB::table('ai_runs')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->where('billing_ledger', 'kinevo')
            ->selectRaw('proposal_type, count(*) as count, coalesce(sum(estimated_cost_minor), 0) as cost')
            ->groupBy('proposal_type')
            ->orderByDesc('count')
            ->get()
            ->map(static fn ($row) => [
                'type' => (string) $row->proposal_type,
                'count' => (int) $row->count,
                'kinevo_cost_minor' => (int) $row->cost,
            ])
            ->all();
    }

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
            'billing_ledger' => $run->billingLedger,
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
            $model->billing_ledger,
        );
    }
}
