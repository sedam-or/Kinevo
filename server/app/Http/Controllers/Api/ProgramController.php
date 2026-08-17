<?php

namespace App\Http\Controllers\Api;

use App\Application\Programs\CreateProgramUseCase;
use App\Application\Programs\GetProgramUseCase;
use App\Application\Programs\ListProgramsUseCase;
use App\Application\Programs\SetProgramStatusUseCase;
use App\Application\Programs\UpdateProgramUseCase;
use App\Domain\Programs\ValueObjects\ProgramStatus;
use App\Domain\Programs\ValueObjects\ProgramWorkloadType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class ProgramController extends Controller
{
    public function __construct(
        private readonly CreateProgramUseCase $createProgramUseCase,
        private readonly ListProgramsUseCase $listProgramsUseCase,
        private readonly GetProgramUseCase $getProgramUseCase,
        private readonly UpdateProgramUseCase $updateProgramUseCase,
        private readonly SetProgramStatusUseCase $setProgramStatusUseCase,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $programs = array_map(
            fn ($program) => $program->toArray(),
            $this->listProgramsUseCase->__invoke($request->user()->id),
        );

        return response()->json(['programs' => $programs]);
    }

    public function show(Request $request, int $programId): JsonResponse
    {
        try {
            $program = $this->getProgramUseCase->__invoke($request->user()->id, $programId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['program' => $program->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:1', 'max:200'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'workload_type' => ['required', 'string', 'in:structured,range,flexible'],
            'weekly_target_minutes' => ['nullable', 'integer', 'min:1'],
            'min_weekly_minutes' => ['nullable', 'integer', 'min:0'],
            'max_weekly_minutes' => ['nullable', 'integer', 'min:0'],
            'priority_tier' => ['sometimes', 'integer', 'between:1,3'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $program = $this->createProgramUseCase->__invoke(
                $request->user()->id,
                $data['name'],
                $data['description'] ?? null,
                $data['category'] ?? null,
                new ProgramWorkloadType($data['workload_type']),
                $data['weekly_target_minutes'] ?? null,
                $data['min_weekly_minutes'] ?? null,
                $data['max_weekly_minutes'] ?? null,
                $data['priority_tier'] ?? 3,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['program' => $program->toArray()], 201);
    }

    public function update(Request $request, int $programId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'workload_type' => ['sometimes', 'string', 'in:structured,range,flexible'],
            'weekly_target_minutes' => ['nullable', 'integer', 'min:1'],
            'min_weekly_minutes' => ['nullable', 'integer', 'min:0'],
            'max_weekly_minutes' => ['nullable', 'integer', 'min:0'],
            'priority_tier' => ['sometimes', 'integer', 'between:1,3'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $program = $this->updateProgramUseCase->__invoke(
                $request->user()->id,
                $programId,
                $data['name'] ?? null,
                $data['description'] ?? null,
                $data['category'] ?? null,
                isset($data['workload_type']) ? new ProgramWorkloadType($data['workload_type']) : null,
                $data['weekly_target_minutes'] ?? null,
                $data['min_weekly_minutes'] ?? null,
                $data['max_weekly_minutes'] ?? null,
                $data['priority_tier'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Program not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['program' => $program->toArray()]);
    }

    public function status(Request $request, int $programId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:active,paused,completed,dropped'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $program = $this->setProgramStatusUseCase->__invoke(
                $request->user()->id,
                $programId,
                new ProgramStatus($validator->validated()['status']),
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Program not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['program' => $program->toArray()]);
    }
}
