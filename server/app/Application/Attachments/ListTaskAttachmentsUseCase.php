<?php

namespace App\Application\Attachments;

use App\Domain\Attachments\Attachment;
use App\Domain\Attachments\Contracts\AttachmentRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use InvalidArgumentException;

/**
 * Lists a task's evidence attachments (FR-43), scoped to the owner.
 */
final readonly class ListTaskAttachmentsUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private AttachmentRepository $attachments,
    ) {}

    /**
     * @return array<int, Attachment>
     */
    public function __invoke(int $userId, int $taskId): array
    {
        $this->assertTaskOwned($userId, $taskId);

        return $this->attachments->listForTask($userId, $taskId);
    }

    private function assertTaskOwned(int $userId, int $taskId): void
    {
        if ($this->tasks->findForUser($userId, $taskId) === null) {
            throw new InvalidArgumentException('Task not found.');
        }
    }
}
