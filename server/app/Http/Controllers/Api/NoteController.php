<?php

namespace App\Http\Controllers\Api;

use App\Application\Knowledge\CreateNoteUseCase;
use App\Application\Knowledge\GetNoteUseCase;
use App\Application\Knowledge\ListNotesUseCase;
use App\Application\Knowledge\UpdateNoteUseCase;
use App\Application\Workspaces\ResolveWorkspaceContext;
use App\Domain\Knowledge\NoteVersionConflict;
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
    ) {}

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

        $note = $this->createNoteUseCase->__invoke(
            $request->user()->id,
            $data['title'],
            $data['document_json'] ?? null,
            $data['markdown_cache'] ?? null,
            $data['plain_text_cache'] ?? null,
            $workspaceId,
        );

        return response()->json(['note' => $note->toArray()], 201);
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
            $note = $this->updateNoteUseCase->__invoke(
                $request->user()->id,
                $noteId,
                $data['base_version'],
                $data['title'] ?? null,
                $data['document_json'] ?? null,
                $data['markdown_cache'] ?? null,
                $data['plain_text_cache'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (NoteVersionConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['note' => $note->toArray()]);
    }
}
