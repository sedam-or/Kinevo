<?php

namespace App\Domain\Attachments\Contracts;

use App\Domain\Attachments\Attachment;

interface AttachmentRepository
{
    public function create(Attachment $attachment): Attachment;

    /**
     * @return array<int, Attachment>
     */
    public function listForTask(int $userId, int $taskId): array;

    public function findForUser(int $userId, int $attachmentId): ?Attachment;

    public function countForTask(int $userId, int $taskId): int;

    public function delete(int $attachmentId): void;
}
