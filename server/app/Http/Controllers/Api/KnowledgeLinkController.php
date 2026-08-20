<?php

namespace App\Http\Controllers\Api;

use App\Application\Knowledge\CreateCanvasLinkUseCase;
use App\Application\Knowledge\CreateNoteLinkUseCase;
use App\Application\Knowledge\ListCanvasLinksUseCase;
use App\Application\Knowledge\ListNoteLinksUseCase;
use App\Application\Knowledge\ListTargetLinksUseCase;
use App\Application\Knowledge\RemoveCanvasLinkUseCase;
use App\Application\Knowledge\RemoveNoteLinkUseCase;
use App\Domain\Knowledge\KnowledgeLinkConflict;
use App\Domain\Knowledge\ValueObjects\KnowledgeLinkType;
use App\Domain\Knowledge\ValueObjects\KnowledgeTargetType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class KnowledgeLinkController extends Controller
{
    public function __construct(
        private readonly CreateNoteLinkUseCase $createNoteLinkUseCase,
        private readonly ListNoteLinksUseCase $listNoteLinksUseCase,
        private readonly ListTargetLinksUseCase $listTargetLinksUseCase,
        private readonly RemoveNoteLinkUseCase $removeNoteLinkUseCase,
        private readonly CreateCanvasLinkUseCase $createCanvasLinkUseCase,
        private readonly ListCanvasLinksUseCase $listCanvasLinksUseCase,
        private readonly RemoveCanvasLinkUseCase $removeCanvasLinkUseCase,
    ) {}

    public function index(Request $request, int $noteId): JsonResponse
    {
        try {
            $links = $this->listNoteLinksUseCase->__invoke($request->user()->id, $noteId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'links' => array_map(fn ($link) => $link->toArray(), $links),
        ]);
    }

    public function store(Request $request, int $noteId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_type' => ['required', 'string', Rule::in(KnowledgeTargetType::all())],
            'target_id' => ['required', 'integer', 'min:1'],
            'link_type' => ['required', 'string', Rule::in(KnowledgeLinkType::all())],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $link = $this->createNoteLinkUseCase->__invoke(
                $request->user()->id,
                $noteId,
                new KnowledgeTargetType($data['target_type']),
                $data['target_id'],
                new KnowledgeLinkType($data['link_type']),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (KnowledgeLinkConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['link' => $link->toArray()], 201);
    }

    public function destroy(Request $request, int $noteId, int $linkId): JsonResponse
    {
        try {
            $this->removeNoteLinkUseCase->__invoke($request->user()->id, $noteId, $linkId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(null, 204);
    }

    public function byTarget(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'target_type' => ['required', 'string', Rule::in(KnowledgeTargetType::all())],
            'target_id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $links = $this->listTargetLinksUseCase->__invoke(
                $request->user()->id,
                new KnowledgeTargetType($data['target_type']),
                $data['target_id'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'links' => array_map(fn ($link) => $link->toArray(), $links),
        ]);
    }

    public function canvasIndex(Request $request, int $canvasId): JsonResponse
    {
        try {
            $links = $this->listCanvasLinksUseCase->__invoke($request->user()->id, $canvasId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'links' => array_map(fn ($link) => $link->toArray(), $links),
        ]);
    }

    public function canvasStore(Request $request, int $canvasId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_type' => ['required', 'string', Rule::in(KnowledgeTargetType::all())],
            'target_id' => ['required', 'integer', 'min:1'],
            'link_type' => ['required', 'string', Rule::in(KnowledgeLinkType::all())],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $link = $this->createCanvasLinkUseCase->__invoke(
                $request->user()->id,
                $canvasId,
                new KnowledgeTargetType($data['target_type']),
                $data['target_id'],
                new KnowledgeLinkType($data['link_type']),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (KnowledgeLinkConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['link' => $link->toArray()], 201);
    }

    public function canvasDestroy(Request $request, int $canvasId, int $linkId): JsonResponse
    {
        try {
            $this->removeCanvasLinkUseCase->__invoke($request->user()->id, $canvasId, $linkId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(null, 204);
    }
}
