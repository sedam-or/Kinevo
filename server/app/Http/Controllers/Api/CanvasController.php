<?php

namespace App\Http\Controllers\Api;

use App\Application\Canvas\CreateCanvasUseCase;
use App\Application\Canvas\GetCanvasUseCase;
use App\Application\Canvas\ListCanvasesUseCase;
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
}
