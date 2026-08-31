<?php

namespace App\Http\Controllers\Api;

use App\Application\Knowledge\CreateNoteUseCase;
use App\Application\Knowledge\GetNoteUseCase;
use App\Application\Knowledge\ListNotesUseCase;
use App\Application\Knowledge\UpdateNoteUseCase;
use App\Application\OfflineSync\OfflineReconciliationService;
use App\Application\Workspaces\ResolveWorkspaceContext;
use App\Domain\Knowledge\NoteVersionConflict;
use App\Domain\OfflineSync\OperationApplyResult;
use App\Domain\OfflineSync\OperationEnvelope;
use App\Domain\OfflineSync\OperationOutcome;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class NoteController extends Controller
{
    public function __construct(
        private readonly CreateNoteUseCase $createNoteUseCase,
        private readonly ListNotesUseCase $listNotesUseCase,
        private readonly ResolveWorkspaceContext $workspaceContext,
        private readonly GetNoteUseCase $getNoteUseCase,
        private readonly UpdateNoteUseCase $updateNoteUseCase,
        private readonly OfflineReconciliationService $reconciliation,
    ) {}

    /**
     * ADR-017 §2.11 — optional online convergence for note mutations.
     */
    private function applyOnlineNoteMutation(
        Request $request,
        string $operationType,
        ?int $noteId,
        array $payload,
        ?int $baseVersion,
        callable $apply,
        int $successStatus,
    ): JsonResponse {
        $operationId = $request->header('X-Operation-Id');
        if ($operationId === null || $operationId === '') {
            return response()->json($apply($request->user()->id), $successStatus);
        }

        $envelope = OperationEnvelope::fromArray([
            'operation_id' => $operationId,
            'operation_type' => $operationType,
            'entity_type' => 'note',
            'entity_id' => $noteId,
            'payload' => $payload,
            'base_version' => $baseVersion,
            'workspace_id' => array_key_exists('workspace_id', $payload) ? $payload['workspace_id'] : null,
        ]);

        $outcome = $this->reconciliation->reconcileOne(
            $request->user()->id,
            $envelope,
            fn () => new OperationApplyResult($apply($request->user()->id), null, null),
        );

        if ($outcome->status === OperationOutcome::CONFLICT) {
            return response()->json(['error' => $outcome->error, 'code' => $outcome->code], 409);
        }

        if ($outcome->replay) {
            $entityId = $outcome->result->entityId;
            if ($entityId !== null) {
                try {
                    $note = $this->getNoteUseCase->__invoke($request->user()->id, $entityId);

                    return response()->json(['note' => $note->toArray()], $successStatus);
                } catch (InvalidArgumentException) {
                    return response()->json(['recorded' => $outcome->result->result], $successStatus);
                }
            }

            return response()->json(['recorded' => $outcome->result->result], $successStatus);
        }

        return response()->json($outcome->result->result, $successStatus);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            // TASK-P19-014 — explicit workspace filter; absent = global view.
            $workspaceId = $this->workspaceContext->forList($request->user()->id, $request->query('workspace_id'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $notes = array_map(
            fn ($note) => $note->toArray(),
            $this->listNotesUseCase->__invoke($request->user()->id, $workspaceId),
        );

        return response()->json(['notes' => $notes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'document_json' => ['nullable', 'array'],
            'markdown_cache' => ['nullable', 'string'],
            'plain_text_cache' => ['nullable', 'string'],
            'workspace_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            // TASK-P19-014 — default context = active workspace (client-declared).
            $workspaceId = $this->workspaceContext->forWrite($request->user()->id, $data['workspace_id'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $payload = $data;
        if (($payload['workspace_id'] ?? null) === null) {
            $payload['workspace_id'] = $workspaceId;
        }

        return $this->applyOnlineNoteMutation(
            $request,
            'note:create',
            null,
            $data,
            null,
            fn (int $userId) => ['note' => $this->createNoteUseCase->__invoke(
                $userId,
                $data['title'],
                $data['document_json'] ?? null,
                $data['markdown_cache'] ?? null,
                $data['plain_text_cache'] ?? null,
                $workspaceId,
            )->toArray()],
            201,
        );
    }

    public function show(Request $request, int $noteId): JsonResponse
    {
        try {
            $note = $this->getNoteUseCase->__invoke($request->user()->id, $noteId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['note' => $note->toArray()]);
    }

    public function update(Request $request, int $noteId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'string', 'min:1', 'max:200'],
            'document_json' => ['sometimes', 'array'],
            'markdown_cache' => ['sometimes', 'nullable', 'string'],
            'plain_text_cache' => ['sometimes', 'nullable', 'string'],
            'base_version' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            return $this->applyOnlineNoteMutation(
                $request,
                'note:update',
                $noteId,
                $data,
                (int) $data['base_version'],
                fn (int $userId) => ['note' => $this->updateNoteUseCase->__invoke(
                    $userId,
                    $noteId,
                    (int) $data['base_version'],
                    $data['title'] ?? null,
                    $data['document_json'] ?? null,
                    $data['markdown_cache'] ?? null,
                    $data['plain_text_cache'] ?? null,
                )->toArray()],
                200,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (NoteVersionConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }
}
