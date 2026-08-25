<?php

namespace App\Application\Workspaces;

use App\Domain\Workspaces\Contracts\WorkspaceRepository;
use App\Domain\Workspaces\Workspace;
use InvalidArgumentException;

/**
 * Single authority for translating the client-declared workspace context
 * into a validated server decision (TASK-P19-006/P19-011..017).
 *
 * Contract (explicit context > active workspace > default):
 * - Writes: an explicit workspace_id is validated (owned + not archived);
 *   when absent, the owner's default workspace is used so every entity
 *   always belongs to exactly one workspace.
 * - Lists: an explicit workspace_id narrows results to that workspace;
 *   `all`/`global` (or absence) means unfiltered — the active-workspace
 *   selection itself lives in the client and travels with each request.
 */
final readonly class ResolveWorkspaceContext
{
    /** Query values that explicitly request the global/unfiltered view. */
    private const GLOBAL_VALUES = ['all', 'global'];

    public function __construct(
        private WorkspaceRepository $workspaces,
        private EnsureDefaultWorkspaceUseCase $ensureDefault,
    ) {}

    /**
     * Workspace id for a WRITE. Validates ownership and archived state.
     *
     * @param  mixed  $workspaceId  raw client input (int|string|null)
     *
     * @throws InvalidArgumentException unknown or archived workspace
     */
    public function forWrite(int $userId, mixed $workspaceId): int
    {
        if ($workspaceId !== null && trim((string) $workspaceId) !== '') {
            $workspace = $this->workspaces->findForUser($userId, (int) $workspaceId);
            if ($workspace === null) {
                throw new InvalidArgumentException('Workspace not found.');
            }
            if ($workspace->status->isArchived()) {
                throw new InvalidArgumentException('This workspace is archived — restore it before adding work.');
            }

            return $workspace->id;
        }

        return $this->defaultWorkspace($userId)->id;
    }

    /**
     * Workspace filter for a LIST: a concrete owned workspace id, or null
     * for the explicit/implicit global view.
     *
     * @param  mixed  $workspaceParam  raw query input (id | 'all' | 'global' | null)
     *
     * @throws InvalidArgumentException numeric but unknown workspace
     */
    public function forList(int $userId, mixed $workspaceParam): ?int
    {
        if ($workspaceParam === null || trim((string) $workspaceParam) === '') {
            return null;
        }
        if (in_array(strtolower(trim((string) $workspaceParam)), self::GLOBAL_VALUES, true)) {
            return null;
        }
        if (! is_numeric($workspaceParam)) {
            throw new InvalidArgumentException('Invalid workspace filter.');
        }

        $workspace = $this->workspaces->findForUser($userId, (int) $workspaceParam);
        if ($workspace === null) {
            throw new InvalidArgumentException('Workspace not found.');
        }

        return $workspace->id;
    }

    private function defaultWorkspace(int $userId): Workspace
    {
        return ($this->ensureDefault)($userId);
    }
}
