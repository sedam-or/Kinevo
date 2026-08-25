<?php

namespace App\Http\Controllers\Api;

use App\Application\Workspaces\ArchiveWorkspaceUseCase;
use App\Application\Workspaces\CreateWorkspaceUseCase;
use App\Application\Workspaces\ListWorkspacesUseCase;
use App\Application\Workspaces\RestoreWorkspaceUseCase;
use App\Application\Workspaces\SetDefaultWorkspaceUseCase;
use App\Application\Workspaces\UpdateWorkspaceUseCase;
use App\Domain\Workspaces\Contracts\WorkspaceRepository;
use App\Domain\Workspaces\Exceptions\DuplicateWorkspaceSlugException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Workspace control plane (TASK-P19-004). Every operation is owner-scoped
 * through $request->user()->id — cross-user access is impossible by
 * construction (TASK-P19-033).
 */
final class WorkspaceController extends Controller
{
    public function __construct(
        private readonly ListWorkspacesUseCase $listWorkspaces,
        private readonly CreateWorkspaceUseCase $createWorkspace,
        private readonly UpdateWorkspaceUseCase $updateWorkspace,
        private readonly ArchiveWorkspaceUseCase $archiveWorkspace,
        private readonly RestoreWorkspaceUseCase $restoreWorkspace,
        private readonly SetDefaultWorkspaceUseCase $setDefaultWorkspace,
        private readonly WorkspaceRepository $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $archived = $request->boolean('include_archived') ? null : false;

        return response()->json([
            'workspaces' => array_map(
                fn ($workspace) => $workspace->toArray(),
                $this->listWorkspaces->__invoke($request->user()->id, $archived),
            ),
            'default_workspace_id' => $this->workspaces->defaultForUser($request->user()->id)?->id,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:32'],
            'accent' => ['nullable', 'string', 'max:16'],
            'type' => ['nullable', 'string', 'max:20'],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $workspace = $this->createWorkspace->__invoke($request->user()->id, $validator->validated());
        } catch (InvalidArgumentException|DuplicateWorkspaceSlugException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['workspace' => $workspace->toArray()], 201);
    }

    public function show(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $workspace = $this->workspaces->findForUser($request->user()->id, $workspaceId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
        if ($workspace === null) {
            return response()->json(['error' => 'Workspace not found.'], 404);
        }

        return response()->json(['workspace' => $workspace->toArray()]);
    }

    public function update(Request $request, int $workspaceId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:32'],
            'accent' => ['nullable', 'string', 'max:16'],
            'type' => ['nullable', 'string', 'max:20'],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $workspace = $this->updateWorkspace->__invoke(
                $request->user()->id,
                $workspaceId,
                $validator->validated(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (DuplicateWorkspaceSlugException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['workspace' => $workspace->toArray()]);
    }

    public function archive(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $workspace = $this->archiveWorkspace->__invoke($request->user()->id, $workspaceId);
        } catch (InvalidArgumentException $e) {
            $status = str_contains($e->getMessage(), 'not found') ? 404 : 422;

            return response()->json(['error' => $e->getMessage()], $status);
        }

        return response()->json(['workspace' => $workspace->toArray()]);
    }

    public function restore(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $workspace = $this->restoreWorkspace->__invoke($request->user()->id, $workspaceId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['workspace' => $workspace->toArray()]);
    }

    public function setDefault(Request $request, int $workspaceId): JsonResponse
    {
        try {
            $workspace = $this->setDefaultWorkspace->__invoke($request->user()->id, $workspaceId);
        } catch (\RuntimeException|InvalidArgumentException $e) {
            $message = $e->getMessage();
            $status = str_contains($message, 'not found') ? 404 : (str_contains($message, 'archived') ? 422 : 409);

            return response()->json(['error' => $message], $status);
        }

        return response()->json(['workspace' => $workspace->toArray()]);
    }
}
