<?php

namespace App\Http\Controllers\Api;

use App\Application\Scheduling\AutoSwapUseCase;
use App\Application\Scheduling\QuickCapturePlacementUseCase;
use App\Application\Scheduling\SetAssignmentLockUseCase;
use App\Application\Tasks\AddSubtaskUseCase;
use App\Application\Tasks\CreateTaskUseCase;
use App\Application\Tasks\GetTaskUseCase;
use App\Application\Tasks\ListTasksUseCase;
use App\Application\Tasks\PartialCompleteTaskUseCase;
use App\Application\Tasks\PromoteSubtaskUseCase;
use App\Application\Tasks\SetTaskStatusUseCase;
use App\Application\Tasks\ToggleSubtaskUseCase;
use App\Application\Tasks\UpdateSubtaskUseCase;
use App\Application\Tasks\UpdateTaskUseCase;
use App\Application\Workspaces\ResolveWorkspaceContext;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignmentVersionConflict;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentStatus;
use App\Domain\Tasks\Contracts\SubtaskRepository;
use App\Domain\Tasks\ValueObjects\TaskStatus;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class TaskController extends Controller
{
    public function __construct(
        private readonly CreateTaskUseCase $createTaskUseCase,
        private readonly ListTasksUseCase $listTasksUseCase,
        private readonly ResolveWorkspaceContext $workspaceContext,
        private readonly GetTaskUseCase $getTaskUseCase,
        private readonly UpdateTaskUseCase $updateTaskUseCase,
        private readonly SetTaskStatusUseCase $setTaskStatusUseCase,
        private readonly AddSubtaskUseCase $addSubtaskUseCase,
        private readonly ToggleSubtaskUseCase $toggleSubtaskUseCase,
        private readonly UpdateSubtaskUseCase $updateSubtaskUseCase,
        private readonly PromoteSubtaskUseCase $promoteSubtaskUseCase,
        private readonly PartialCompleteTaskUseCase $partialCompleteTaskUseCase,
        private readonly QuickCapturePlacementUseCase $quickCapturePlacementUseCase,
        private readonly AutoSwapUseCase $autoSwapUseCase,
        private readonly SetAssignmentLockUseCase $setAssignmentLockUseCase,
        private readonly ScheduleAssignmentRepository $assignments,
        private readonly SubtaskRepository $subtaskRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            // TASK-P19-013 — explicit workspace filter; absent = global view.
            $workspaceId = $this->workspaceContext->forList($request->user()->id, $request->query('workspace_id'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $tasks = array_map(
            fn ($task) => $task->toArray(),
            $this->listTasksUseCase->__invoke($request->user()->id, $workspaceId),
        );

        return response()->json(['tasks' => $tasks]);
    }

    public function quickCapture(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'priority_tier' => ['sometimes', 'integer', 'between:1,3'],
            'size' => ['sometimes', 'string', 'in:cepat,sedang,berat'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'program_id' => ['nullable', 'integer'],
            'goal_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
            'workspace_id' => ['nullable', 'integer', 'min:1'],
            'is_sacred_anchor' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $result = $this->quickCapturePlacementUseCase->__invoke(
                $request->user()->id,
                $data['title'],
                $data['priority_tier'] ?? 3,
                $data['size'] ?? null,
                $data['duration_minutes'] ?? null,
                $data['program_id'] ?? null,
                $data['goal_id'] ?? null,
                isset($data['date']) ? CarbonImmutable::parse($data['date']) : null,
                // TASK-P19-024 — raw context; precedence owned by the use case.
                $data['workspace_id'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $status = $result->placed ? 201 : 200;

        return response()->json($result->toArray(), $status);
    }

    public function autoSwap(Request $request, int $taskId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $result = $this->autoSwapUseCase->__invoke(
                $request->user()->id,
                $taskId,
                CarbonImmutable::parse($data['date']),
                (int) $data['duration_minutes'],
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Task not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result->toArray(), $result->applied ? 200 : 202);
    }

    /**
     * ADR-015 locked-task contract: the user fixes a placement. Locked
     * placements are never moved by the scheduler or the rescheduler.
     */
    public function lock(Request $request, int $taskId): JsonResponse
    {
        return $this->setAssignmentLock($request, $taskId, true);
    }

    public function unlock(Request $request, int $taskId): JsonResponse
    {
        return $this->setAssignmentLock($request, $taskId, false);
    }

    private function setAssignmentLock(Request $request, int $taskId, bool $locked): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'version' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $assignment = $this->setAssignmentLockUseCase->__invoke(
                $request->user()->id,
                $taskId,
                $locked,
                isset($data['version']) ? (int) $data['version'] : null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Task not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 404);
        } catch (ScheduleAssignmentVersionConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['assignment' => $assignment->toArray()]);
    }

    public function show(Request $request, int $taskId): JsonResponse
    {
        try {
            $task = $this->getTaskUseCase->__invoke($request->user()->id, $taskId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        // ADR-015 additive: the task's active placement lock state, so the
        // UI can hydrate the lock control without an extra request.
        $locked = null;
        foreach ($this->assignments->listForTask($taskId) as $assignment) {
            if ($assignment->userId === $request->user()->id
                && $assignment->status->equals(ScheduleAssignmentStatus::scheduled())) {
                $locked = $assignment->locked;
                break;
            }
        }

        return response()->json([
            'task' => $task->toArray(),
            'assignment_locked' => $locked,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'description' => ['nullable', 'string'],
            'program_id' => ['nullable', 'integer'],
            'goal_id' => ['nullable', 'integer'],
            'milestone_id' => ['nullable', 'integer'],
            'priority_tier' => ['sometimes', 'integer', 'between:1,3'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'due_at' => ['nullable', 'date'],
            'workspace_id' => ['nullable', 'integer', 'min:1'],
            'is_sacred_anchor' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $task = $this->createTaskUseCase->__invoke(
                $request->user()->id,
                $data['title'],
                $data['description'] ?? null,
                $data['program_id'] ?? null,
                $data['goal_id'] ?? null,
                $data['milestone_id'] ?? null,
                $data['priority_tier'] ?? 3,
                $data['estimated_minutes'] ?? null,
                isset($data['due_at']) ? CarbonImmutable::parse($data['due_at']) : null,
                // TASK-P19-013/024 — raw context; the use case owns the
                // precedence rules (explicit > inherited > default).
                $data['workspace_id'] ?? null,
                $data['is_sacred_anchor'] ?? false,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['task' => $task->toArray()], 201);
    }

    public function update(Request $request, int $taskId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'program_id' => ['sometimes', 'nullable', 'integer'],
            'goal_id' => ['sometimes', 'nullable', 'integer'],
            'milestone_id' => ['sometimes', 'nullable', 'integer'],
            'priority_tier' => ['sometimes', 'integer', 'between:1,3'],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'is_sacred_anchor' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $task = $this->updateTaskUseCase->__invoke(
                $request->user()->id,
                $taskId,
                $data['title'] ?? null,
                $data['description'] ?? null,
                $data['program_id'] ?? null,
                $data['goal_id'] ?? null,
                $data['milestone_id'] ?? null,
                $data['priority_tier'] ?? null,
                $data['estimated_minutes'] ?? null,
                isset($data['due_at']) ? CarbonImmutable::parse($data['due_at']) : null,
                $data['is_sacred_anchor'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Task not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['task' => $task->toArray()]);
    }

    public function status(Request $request, int $taskId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:backlog,scheduled,in_progress,partial,continued,completed,skipped,missed,conflict'],
            'version' => ['sometimes', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('version')) {
            try {
                $current = $this->getTaskUseCase->__invoke($request->user()->id, $taskId);
            } catch (InvalidArgumentException) {
                $current = null;
            }

            if ($current !== null && $current->version !== $request->integer('version')) {
                return response()->json([
                    'error' => 'Task changed on another device — reload and retry.',
                    'code' => 'VERSION_CONFLICT',
                ], 409);
            }
        }

        try {
            $task = $this->setTaskStatusUseCase->__invoke(
                $request->user()->id,
                $taskId,
                new TaskStatus($validator->validated()['status']),
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Task not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['task' => $task->toArray()]);
    }

    public function partialComplete(Request $request, int $taskId): JsonResponse
    {
        try {
            $result = $this->partialCompleteTaskUseCase->__invoke($request->user()->id, $taskId);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Task not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'task' => $result['task']->toArray(),
            'continuation' => $result['continuation']?->toArray(),
        ]);
    }

    public function addSubtask(Request $request, int $taskId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'notes' => ['nullable', 'string'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $subtask = $this->addSubtaskUseCase->__invoke(
                $request->user()->id,
                $taskId,
                $data['title'],
                $data['notes'] ?? null,
                $data['sequence'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Task not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['subtask' => $subtask->toArray()], 201);
    }

    public function subtasks(Request $request, int $taskId): JsonResponse
    {
        try {
            $this->getTaskUseCase->__invoke($request->user()->id, $taskId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $subtasks = array_map(
            fn ($subtask) => $subtask->toArray(),
            $this->subtaskRepository->listForTask($request->user()->id, $taskId),
        );

        return response()->json(['subtasks' => $subtasks]);
    }

    public function toggleSubtask(Request $request, int $taskId, int $subtaskId): JsonResponse
    {
        try {
            $result = $this->toggleSubtaskUseCase->__invoke($request->user()->id, $subtaskId);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Subtask not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'subtask' => $result['subtask']->toArray(),
            'task' => $result['task']->toArray(),
        ]);
    }

    public function updateSubtask(Request $request, int $taskId, int $subtaskId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'min:1', 'max:200'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $result = $this->updateSubtaskUseCase->__invoke(
                $request->user()->id,
                $subtaskId,
                $data['title'] ?? null,
                $data['notes'] ?? null,
                $data['sequence'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Subtask not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'subtask' => $result['subtask']->toArray(),
            'task' => $result['task']->toArray(),
        ]);
    }

    public function promoteSubtask(Request $request, int $subtaskId): JsonResponse
    {
        try {
            $result = $this->promoteSubtaskUseCase->__invoke($request->user()->id, $subtaskId);
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Subtask not found.') {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'task' => $result['task']->toArray(),
            'source_task' => $result['source_task']->toArray(),
        ]);
    }
}
