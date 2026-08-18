<?php

namespace App\Application\Knowledge;

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

final class CreateNoteLinkUseCase
{
    public function __construct(
        private readonly NoteRepository $notes,
        private readonly KnowledgeLinkRepository $links,
        private readonly GoalRepository $goals,
        private readonly MilestoneRepository $milestones,
        private readonly ProgramRepository $programs,
        private readonly TaskRepository $tasks,
    ) {}

    public function __invoke(
        int $userId,
        int $noteId,
        KnowledgeTargetType $targetType,
        int $targetId,
        KnowledgeLinkType $linkType,
    ): KnowledgeLink {
        $note = $this->notes->findForUser($userId, $noteId);
        if ($note === null) {
            throw new InvalidArgumentException("Note not found: {$noteId}");
        }

        $this->assertTargetOwned($userId, $targetType, $targetId);

        $link = KnowledgeLink::create(
            $userId,
            KnowledgeLink::SOURCE_NOTE,
            $noteId,
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
            default => false,
        };

        if (! $exists) {
            throw new InvalidArgumentException(
                "Knowledge link target not found: {$targetType->value} {$targetId}"
            );
        }
    }
}
