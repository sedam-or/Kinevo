<?php

namespace App\Application\Attachments;

use App\Domain\Attachments\Attachment;
use App\Domain\Attachments\AttachmentRule;
use App\Domain\Attachments\Contracts\AttachmentRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Uploads an evidence attachment to a completed task (FR-43). Enforces the
 * allowlist extension + detected content type + size (SRS line 1641); the
 * browser-provided MIME is never trusted on its own. Files are stored on a
 * private disk under a generated name and are not world-readable (SRS 1653).
 * Storage failure never leaves a dangling DB record (FR-43 Exception Flows).
 */
final readonly class UploadTaskAttachmentUseCase
{
    public const DISK = 'local';

    public function __construct(
        private TaskRepository $tasks,
        private AttachmentRepository $attachments,
    ) {}

    public function __invoke(int $userId, int $taskId, UploadedFile $file): Attachment
    {
        $task = $this->tasks->findForUser($userId, $taskId);
        if ($task === null) {
            throw new InvalidArgumentException('Task not found.');
        }

        if (! $task->isCompleted()) {
            throw new InvalidArgumentException('Evidence attachments can only be added to completed tasks.');
        }

        if ($this->attachments->countForTask($userId, $taskId) >= AttachmentRule::MAX_PER_TASK) {
            throw new InvalidArgumentException('A task can have at most 3 attachments.');
        }

        if ($file->getSize() > AttachmentRule::MAX_BYTES) {
            throw new InvalidArgumentException('Attachment exceeds the 5 MB size limit.');
        }

        if (! AttachmentRule::isAllowedExtension((string) $file->getClientOriginalExtension())) {
            throw new InvalidArgumentException('Only JPG, PNG, and PDF files are allowed.');
        }

        $detectedMime = strtolower((string) $file->getMimeType());
        if (! AttachmentRule::isAllowedMime($detectedMime)) {
            throw new InvalidArgumentException('Unsupported file content type.');
        }

        $originalName = $file->getClientOriginalName() ?: 'attachment';
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $storedName = 'attachments/'.Str::uuid()->toString().'.'.$extension;
        $contents = (string) file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $contents);

        try {
            Storage::disk(self::DISK)->put($storedName, $contents);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Attachment storage failed.', 0, $e);
        }

        $attachment = Attachment::create(
            $userId,
            $taskId,
            $originalName,
            $storedName,
            self::DISK,
            $detectedMime,
            $file->getSize(),
            $sha256,
        );

        try {
            return $this->attachments->create($attachment);
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($storedName);
            throw $e;
        }
    }

    /**
     * Storage files are private (SRS 1653); the physical path is only ever
     * resolved through the domain metadata and never exposed to the client.
     */
    public static function stream(Attachment $attachment)
    {
        return Storage::disk($attachment->disk)->response($attachment->storedName, $attachment->filename);
    }
}
