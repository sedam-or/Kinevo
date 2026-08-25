<?php

namespace App\Application\Workspaces;

use App\Domain\Workspaces\Contracts\WorkspaceRepository;
use App\Domain\Workspaces\Workspace;
use InvalidArgumentException;

/** Exactly-one-default invariant (TASK-P19-001). */
final readonly class SetDefaultWorkspaceUseCase
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

        $this->workspaces->setDefault($userId, $workspaceId);

        return $this->workspaces->findForUser($userId, $workspaceId) ?? throw new InvalidArgumentException('Workspace not found.');
    }
}
