<?php

namespace App\Application\Workspaces;

use App\Domain\Workspaces\Contracts\WorkspaceRepository;
use App\Domain\Workspaces\Workspace;
use InvalidArgumentException;

/**
 * TASK-P19-030 — archive preserves all data, removes the workspace from
 * active switching, prevents new scoped work and is always reversible.
 * The default workspace can never be archived.
 */
final readonly class ArchiveWorkspaceUseCase
{
    public function __construct(
        private WorkspaceRepository $workspaces,
    ) {}

    public function __invoke(int $userId, int $workspaceId): Workspace
    {
        $existing = $this->workspaces->findForUser($userId, $workspaceId);
        if ($existing === null) {
            throw new InvalidArgumentException('Workspace not found.');
        }

        return $this->workspaces->update($existing->archive());
    }
}
