<?php

namespace App\Application\Attachments;

use App\Domain\Attachments\Contracts\AttachmentRepository;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Deletes an evidence attachment (FR-43): removes the private file and the
 * metadata record, scoped to the owner.
 */
final readonly class DeleteTaskAttachmentUseCase
{
    public function __construct(
        private AttachmentRepository $attachments,
    ) {}

    public function __invoke(int $userId, int $taskId, int $attachmentId): void
    {
        $attachment = $this->attachments->findForUser($userId, $attachmentId);

        if ($attachment === null || $attachment->taskId !== $taskId) {
            throw new InvalidArgumentException('Attachment not found.');
        }

        $this->attachments->delete($attachmentId);
        Storage::disk($attachment->disk)->delete($attachment->storedName);
    }
}
