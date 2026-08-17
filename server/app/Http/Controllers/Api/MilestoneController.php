<?php

namespace App\Http\Controllers\Api;

use App\Application\Milestones\CreateMilestoneUseCase;
use App\Application\Milestones\GetMilestoneUseCase;
use App\Application\Milestones\ListMilestonesUseCase;
use App\Application\Milestones\ReorderMilestonesUseCase;
use App\Application\Milestones\SetMilestoneStatusUseCase;
use App\Application\Milestones\UpdateMilestoneUseCase;
use App\Domain\Milestones\ValueObjects\MilestoneStatus;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class MilestoneController extends Controller
{
    public function __construct(
        private readonly CreateMilestoneUseCase $createMilestoneUseCase,
        private readonly ListMilestonesUseCase $listMilestonesUseCase,
        private readonly GetMilestoneUseCase $getMilestoneUseCase,
        private readonly UpdateMilestoneUseCase $updateMilestoneUseCase,
        private readonly SetMilestoneStatusUseCase $setMilestoneStatusUseCase,
        private readonly ReorderMilestonesUseCase $reorderMilestonesUseCase,
    ) {}

    public function index(Request $request, int $goalId): JsonResponse
    {
        try {
            $milestones = array_map(
                fn ($milestone) => $milestone->toArray(),
                $this->listMilestonesUseCase->__invoke($request->user()->id, $goalId),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['milestones' => $milestones]);
    }

    public function show(Request $request, int $milestoneId): JsonResponse
    {
        try {
            $milestone = $this->getMilestoneUseCase->__invoke($request->user()->id, $milestoneId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['milestone' => $milestone->toArray()]);
    }

    public function store(Request $request, int $goalId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'description' => ['nullable', 'string'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
            'target_date' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $milestone = $this->createMilestoneUseCase->__invoke(
                $request->user()->id,
                $goalId,
                $data['title'],
                $data['description'] ?? null,
                $data['sequence'] ?? null,
                isset($data['target_date']) ? CarbonImmutable::parse($data['target_date']) : null,
                $data['estimated_minutes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Goal not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['milestone' => $milestone->toArray()], 201);
    }

    public function update(Request $request, int $goalId, int $milestoneId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
            'target_date' => ['sometimes', 'nullable', 'date'],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $milestone = $this->updateMilestoneUseCase->__invoke(
                $request->user()->id,
                $milestoneId,
                $data['title'] ?? null,
                $data['description'] ?? null,
                $data['sequence'] ?? null,
                isset($data['target_date']) ? CarbonImmutable::parse($data['target_date']) : null,
                $data['estimated_minutes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Milestone not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['milestone' => $milestone->toArray()]);
    }

    public function status(Request $request, int $goalId, int $milestoneId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:planned,active,blocked,completed,dropped'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $milestone = $this->setMilestoneStatusUseCase->__invoke(
                $request->user()->id,
                $milestoneId,
                new MilestoneStatus($validator->validated()['status']),
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Milestone not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['milestone' => $milestone->toArray()]);
    }

    public function reorder(Request $request, int $goalId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->reorderMilestonesUseCase->__invoke(
                $request->user()->id,
                $goalId,
                $validator->validated()['ordered_ids'],
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Goal not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Milestones reordered.']);
    }
}
