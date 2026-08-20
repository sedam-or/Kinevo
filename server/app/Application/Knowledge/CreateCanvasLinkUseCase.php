<?php

namespace App\Application\Knowledge;

use App\Domain\Canvas\Contracts\CanvasRepository;
use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Knowledge\Contracts\KnowledgeLinkRepository;
use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Knowledge\KnowledgeLink;
use App\Domain\Knowledge\ValueObjects\KnowledgeLinkType;
use App\Domain\Knowledge\ValueObjects\KnowledgeTargetType;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use InvalidArgumentException;

final class CreateCanvasLinkUseCase
{
    public function __construct(
        private readonly CanvasRepository $canvases,
        private readonly KnowledgeLinkRepository $links,
        private readonly GoalRepository $goals,
        private readonly MilestoneRepository $milestones,
        private readonly ProgramRepository $programs,
        private readonly TaskRepository $tasks,
        private readonly NoteRepository $notes,
    ) {}

    public function __invoke(
        int $userId,
        int $canvasId,
        KnowledgeTargetType $targetType,
        int $targetId,
        KnowledgeLinkType $linkType,
    ): KnowledgeLink {
        $canvas = $this->canvases->findForUser($userId, $canvasId);
        if ($canvas === null) {
            throw new InvalidArgumentException("Canvas not found: {$canvasId}");
        }

        $this->assertTargetOwned($userId, $targetType, $targetId);

        $link = KnowledgeLink::create(
            $userId,
            KnowledgeLink::SOURCE_CANVAS,
            $canvasId,
            $targetType,
            $targetId,
            $linkType,
        );

        return $this->links->create($userId, $link);
    }

    private function assertTargetOwned(int $userId, KnowledgeTargetType $targetType, int $targetId): void
    {
        $exists = match ($targetType->value) {
            KnowledgeTargetType::GOAL => $this->goals->findForUser($userId, $targetId) !== null,
            KnowledgeTargetType::MILESTONE => $this->milestones->findForUser($userId, $targetId) !== null,
            KnowledgeTargetType::PROGRAM => $this->programs->findForUser($userId, $targetId) !== null,
            KnowledgeTargetType::TASK => $this->tasks->findForUser($userId, $targetId) !== null,
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
