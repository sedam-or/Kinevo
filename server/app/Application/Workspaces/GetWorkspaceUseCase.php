<?php

namespace App\Application\Workspaces;

use App\Domain\Workspaces\Contracts\WorkspaceRepository;
use App\Domain\Workspaces\Workspace;
use InvalidArgumentException;

final readonly class GetWorkspaceUseCase
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

        return $existing;
    }
}
