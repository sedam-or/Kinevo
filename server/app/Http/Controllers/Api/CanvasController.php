<?php

namespace App\Http\Controllers\Api;

use App\Application\Canvas\AddCanvasFileUseCase;
use App\Application\Canvas\ArchiveCanvasUseCase;
use App\Application\Canvas\CreateCanvasUseCase;
use App\Application\Canvas\GetCanvasUseCase;
use App\Application\Canvas\ListCanvasesUseCase;
use App\Application\Canvas\ListCanvasFilesUseCase;
use App\Application\Canvas\RenameCanvasUseCase;
use App\Application\Canvas\SaveCanvasUseCase;
use App\Domain\Canvas\CanvasVersionConflict;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class CanvasController extends Controller
{
    public function __construct(
        private readonly CreateCanvasUseCase $createCanvasUseCase,
        private readonly ListCanvasesUseCase $listCanvasesUseCase,
        private readonly GetCanvasUseCase $getCanvasUseCase,
        private readonly SaveCanvasUseCase $saveCanvasUseCase,
        private readonly RenameCanvasUseCase $renameCanvasUseCase,
        private readonly ArchiveCanvasUseCase $archiveCanvasUseCase,
        private readonly AddCanvasFileUseCase $addCanvasFileUseCase,
        private readonly ListCanvasFilesUseCase $listCanvasFilesUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $canvases = $this->listCanvasesUseCase->__invoke($request->user()->id);

        return response()->json([
            'canvases' => array_map(fn ($canvas) => $canvas->toArray(), $canvases),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'goal_id' => ['nullable', 'integer', 'min:1'],
            'milestone_id' => ['nullable', 'integer', 'min:1'],
            'program_id' => ['nullable', 'integer', 'min:1'],
            'task_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $canvas = $this->createCanvasUseCase->__invoke(
            $request->user()->id,
            $data['title'],
            $data['goal_id'] ?? null,
            $data['milestone_id'] ?? null,
            $data['program_id'] ?? null,
            $data['task_id'] ?? null,
        );

        return response()->json(['canvas' => $canvas->toArray()], 201);
    }

    public function show(Request $request, int $canvasId): JsonResponse
    {
        try {
            $result = $this->getCanvasUseCase->__invoke($request->user()->id, $canvasId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'canvas' => $result['canvas']->toArray(),
            'document' => $result['document']?->toArray(),
        ]);
    }

    public function save(Request $request, int $canvasId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'scene_json' => ['required', 'array'],
            'base_version' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $document = $this->saveCanvasUseCase->__invoke(
                $request->user()->id,
                $canvasId,
                $data['base_version'],
                $data['scene_json'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (CanvasVersionConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['document' => $document->toArray()]);
    }

    public function rename(Request $request, int $canvasId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'min:1', 'max:200'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $canvas = $this->renameCanvasUseCase->__invoke(
                $request->user()->id,
                $canvasId,
                $validator->validated()['title'],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['canvas' => $canvas->toArray()]);
    }

    public function archive(Request $request, int $canvasId): JsonResponse
    {
        try {
            $canvas = $this->archiveCanvasUseCase->__invoke(
                $request->user()->id,
                $canvasId,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['canvas' => $canvas->toArray()]);
    }

    public function files(Request $request, int $canvasId): JsonResponse
    {
        try {
            $files = $this->listCanvasFilesUseCase->__invoke($request->user()->id, $canvasId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'files' => array_map(fn ($file) => $file->toArray(), $files),
        ]);
    }

    public function addFile(Request $request, int $canvasId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'storage_path' => ['required', 'string', 'min:1'],
            'content_type' => ['required', 'string', 'min:1'],
            'size_bytes' => ['required', 'integer', 'min:0'],
            'sha256' => ['nullable', 'string', 'size:64'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $file = $this->addCanvasFileUseCase->__invoke(
                $request->user()->id,
                $canvasId,
                $data['storage_path'],
                $data['content_type'],
                $data['size_bytes'],
                $data['sha256'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['file' => $file->toArray()], 201);
    }
}
