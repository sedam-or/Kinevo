<?php

namespace App\Application\Attachments;

use App\Domain\Attachments\Attachment;
use App\Domain\Attachments\Contracts\AttachmentRepository;
use InvalidArgumentException;

/**
 * Resolves an evidence attachment for download (FR-43), scoped to the owner.
 */
final readonly class GetTaskAttachmentUseCase
{
    public function __construct(
        private AttachmentRepository $attachments,
    ) {}

    public function __invoke(int $userId, int $taskId, int $attachmentId): Attachment
    {
        $attachment = $this->attachments->findForUser($userId, $attachmentId);

        if ($attachment === null || $attachment->taskId !== $taskId) {
            throw new InvalidArgumentException('Attachment not found.');
        }

        return $attachment;
    }
}
