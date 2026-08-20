<?php

namespace App\Domain\Canvas;

use DateTimeImmutable;
use InvalidArgumentException;

final class Canvas
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $title,
        public readonly ?int $goalId,
        public readonly ?int $milestoneId,
        public readonly ?int $programId,
        public readonly ?int $taskId,
        public readonly int $version,
        public readonly ?DateTimeImmutable $archivedAt = null,
    ) {}

    public static function create(
        int $userId,
        string $title,
        ?int $goalId = null,
        ?int $milestoneId = null,
        ?int $programId = null,
        ?int $taskId = null,
    ): self {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Canvas title is required.');
        }

        return new self(
            0,
            $userId,
            trim($title),
            $goalId,
            $milestoneId,
            $programId,
            $taskId,
            1,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->title,
            $this->goalId,
            $this->milestoneId,
            $this->programId,
            $this->taskId,
            $this->version,
            $this->archivedAt,
        );
    }

    public function withTitle(string $title): self
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Canvas title is required.');
        }

        return new self(
            $this->id,
            $this->userId,
            trim($title),
            $this->goalId,
            $this->milestoneId,
            $this->programId,
            $this->taskId,
            $this->version + 1,
            $this->archivedAt,
        );
    }

    public function withContext(
        ?int $goalId = null,
        ?int $milestoneId = null,
        ?int $programId = null,
        ?int $taskId = null,
    ): self {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $goalId,
            $milestoneId,
            $programId,
            $taskId,
            $this->version + 1,
            $this->archivedAt,
        );
    }

    public function withVersion(int $version): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->goalId,
            $this->milestoneId,
            $this->programId,
            $this->taskId,
            $version,
            $this->archivedAt,
        );
    }

    public function archive(DateTimeImmutable $archivedAt): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->goalId,
            $this->milestoneId,
            $this->programId,
            $this->taskId,
            $this->version + 1,
            $archivedAt,
        );
    }

    public function restore(): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $this->goalId,
            $this->milestoneId,
            $this->programId,
            $this->taskId,
            $this->version + 1,
            null,
        );
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'goal_id' => $this->goalId,
            'milestone_id' => $this->milestoneId,
            'program_id' => $this->programId,
            'task_id' => $this->taskId,
            'version' => $this->version,
            'archived_at' => $this->archivedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
