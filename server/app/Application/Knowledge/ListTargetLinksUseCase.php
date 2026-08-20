<?php

namespace App\Application\Knowledge;

use App\Domain\Canvas\Contracts\CanvasRepository;
use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Knowledge\Contracts\KnowledgeLinkRepository;
use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Knowledge\KnowledgeLink;
use App\Domain\Knowledge\ValueObjects\KnowledgeTargetType;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use InvalidArgumentException;

final class ListTargetLinksUseCase
{
    public function __construct(
        private readonly KnowledgeLinkRepository $links,
        private readonly GoalRepository $goals,
        private readonly MilestoneRepository $milestones,
        private readonly ProgramRepository $programs,
        private readonly TaskRepository $tasks,
        private readonly CanvasRepository $canvases,
        private readonly NoteRepository $notes,
    ) {}

    /**
     * Reverse navigation: every knowledge item linked to a domain object owned
     * by the user (FR-54 AC).
     *
     * @return list<KnowledgeLink>
     */
    public function __invoke(
        int $userId,
        KnowledgeTargetType $targetType,
        int $targetId,
    ): array {
        $this->assertTargetOwned($userId, $targetType, $targetId);

        return $this->links->listForTarget($userId, $targetType->value, $targetId);
    }

    private function assertTargetOwned(int $userId, KnowledgeTargetType $targetType, int $targetId): void
    {
        $exists = match ($targetType->value) {
            KnowledgeTargetType::GOAL => $this->goals->findForUser($userId, $targetId) !== null,
            KnowledgeTargetType::MILESTONE => $this->milestones->findForUser($userId, $targetId) !== null,
            KnowledgeTargetType::PROGRAM => $this->programs->findForUser($userId, $targetId) !== null,
            KnowledgeTargetType::TASK => $this->tasks->findForUser($userId, $targetId) !== null,
            KnowledgeTargetType::CANVAS => $this->canvases->findForUser($userId, $targetId) !== null,
            KnowledgeTargetType::NOTE => $this->notes->findForUser($userId, $targetId) !== null,
            default => false,
        };

        if (! $exists) {
            throw new InvalidArgumentException(
                "Knowledge link target not found: {$targetType->value} {$targetId}"
            );
        }
    }
}
