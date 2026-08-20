<?php

namespace App\Http\Controllers\Api;

use App\Application\Attachments\DeleteTaskAttachmentUseCase;
use App\Application\Attachments\GetTaskAttachmentUseCase;
use App\Application\Attachments\ListTaskAttachmentsUseCase;
use App\Application\Attachments\UploadTaskAttachmentUseCase;
use App\Domain\Attachments\AttachmentRule;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Evidence attachments on tasks (FR-43): upload (max 3, JPG/PNG/PDF, ≤5 MB,
 * detected content type), list, download, delete. All operations are scoped to
 * the authenticated owner and files are private (not world-readable).
 */
final class AttachmentController extends Controller
{
    public function __construct(
        private readonly UploadTaskAttachmentUseCase $upload,
        private readonly ListTaskAttachmentsUseCase $list,
        private readonly GetTaskAttachmentUseCase $get,
        private readonly DeleteTaskAttachmentUseCase $delete,
    ) {}

    public function store(Request $request, int $taskId): JsonResponse
    {
        $file = $request->file('file');

        if ($file === null) {
            return response()->json(['errors' => ['file' => ['A file is required.']]], 422);
        }

        try {
            $attachment = $this->upload->__invoke($request->user()->id, $taskId, $file);

            return response()->json(['attachment' => $attachment->toArray()], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['errors' => ['file' => [$e->getMessage()]]], 422);
        }
    }

    public function index(Request $request, int $taskId): JsonResponse
    {
        try {
            $attachments = array_map(
                static fn ($attachment) => $attachment->toArray(),
                $this->list->__invoke($request->user()->id, $taskId),
            );

            return response()->json(['attachments' => $attachments]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['errors' => ['task' => [$e->getMessage()]]], 404);
        }
    }

    public function show(Request $request, int $taskId, int $attachmentId)
    {
        try {
            $attachment = $this->get->__invoke($request->user()->id, $taskId, $attachmentId);

            return UploadTaskAttachmentUseCase::stream($attachment);
        } catch (InvalidArgumentException $e) {
            return response()->json(['errors' => ['attachment' => [$e->getMessage()]]], 404);
        }
    }

    public function destroy(Request $request, int $taskId, int $attachmentId): JsonResponse
    {
        try {
            $this->delete->__invoke($request->user()->id, $taskId, $attachmentId);

            return response()->json(['deleted' => true]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['errors' => ['attachment' => [$e->getMessage()]]], 404);
        }
    }

    /**
     * Expose the rule limits for the client (FR-43 AC: 4th file / >5 MB reject).
     */
    public function rules(): JsonResponse
    {
        return response()->json([
            'max_per_task' => AttachmentRule::MAX_PER_TASK,
            'max_bytes' => AttachmentRule::MAX_BYTES,
            'allowed_extensions' => AttachmentRule::ALLOWED_EXTENSIONS,
            'allowed_mime' => AttachmentRule::ALLOWED_MIME,
        ]);
    }
}
