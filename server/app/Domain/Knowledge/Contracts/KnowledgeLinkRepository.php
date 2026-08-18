<?php

namespace App\Domain\Knowledge\Contracts;

use App\Domain\Knowledge\KnowledgeLink;

interface KnowledgeLinkRepository
{
    /**
     * Persist a new knowledge link. Throws KnowledgeLinkConflict when the exact
     * link already exists for this user.
     */
    public function create(int $userId, KnowledgeLink $link): KnowledgeLink;

    public function findForUser(int $userId, int $linkId): ?KnowledgeLink;

    /**
     * @return list<KnowledgeLink>
     */
    public function listForSource(int $userId, string $sourceType, int $sourceId): array;

    /**
     * @return list<KnowledgeLink>
     */
    public function listForTarget(int $userId, string $targetType, int $targetId): array;

    public function remove(int $userId, int $linkId): void;
}
