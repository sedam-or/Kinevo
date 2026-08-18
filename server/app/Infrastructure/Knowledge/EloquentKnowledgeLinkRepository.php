<?php

namespace App\Infrastructure\Knowledge;

use App\Domain\Knowledge\Contracts\KnowledgeLinkRepository;
use App\Domain\Knowledge\KnowledgeLink;
use App\Domain\Knowledge\KnowledgeLinkConflict;
use App\Domain\Knowledge\ValueObjects\KnowledgeLinkType;
use App\Domain\Knowledge\ValueObjects\KnowledgeTargetType;
use App\Models\KnowledgeLink as KnowledgeLinkModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentKnowledgeLinkRepository implements KnowledgeLinkRepository
{
    public function create(int $userId, KnowledgeLink $link): KnowledgeLink
    {
        $duplicate = KnowledgeLinkModel::query()
            ->where('user_id', $userId)
            ->where('source_type', $link->sourceType)
            ->where('source_id', $link->sourceId)
            ->where('target_type', $link->targetType->value)
            ->where('target_id', $link->targetId)
            ->where('link_type', $link->linkType->value)
            ->exists();

        if ($duplicate) {
            throw KnowledgeLinkConflict::duplicate();
        }

        $model = KnowledgeLinkModel::query()->create([
            'user_id' => $userId,
            'source_type' => $link->sourceType,
            'source_id' => $link->sourceId,
            'target_type' => $link->targetType->value,
            'target_id' => $link->targetId,
            'link_type' => $link->linkType->value,
        ]);

        return $this->toDomain($model);
    }

    public function findForUser(int $userId, int $linkId): ?KnowledgeLink
    {
        $model = KnowledgeLinkModel::query()->where('user_id', $userId)->find($linkId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function listForSource(int $userId, string $sourceType, int $sourceId): array
    {
        return KnowledgeLinkModel::query()
            ->where('user_id', $userId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->orderBy('id')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function listForTarget(int $userId, string $targetType, int $targetId): array
    {
        return KnowledgeLinkModel::query()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->orderBy('id')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function remove(int $userId, int $linkId): void
    {
        try {
            KnowledgeLinkModel::query()->where('user_id', $userId)->findOrFail($linkId)->delete();
        } catch (ModelNotFoundException) {
            throw new \InvalidArgumentException('Knowledge link not found.');
        }
    }

    private function toDomain(KnowledgeLinkModel $model): KnowledgeLink
    {
        return new KnowledgeLink(
            $model->id,
            $model->user_id,
            $model->source_type,
            $model->source_id,
            new KnowledgeTargetType($model->target_type),
            $model->target_id,
            new KnowledgeLinkType($model->link_type),
        );
    }
}
