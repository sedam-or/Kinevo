<?php

namespace App\Application\Workspaces;

use App\Domain\Workspaces\Contracts\WorkspaceRepository;
use App\Domain\Workspaces\Workspace;

final readonly class ListWorkspacesUseCase
{
    public function __construct(
        private WorkspaceRepository $workspaces,
        private EnsureDefaultWorkspaceUseCase $ensureDefault,
    ) {}

    /**
     * @return array<int, Workspace>
     */
    public function __invoke(int $userId, ?bool $archived = null): array
    {
        // Lazy safety net: a user without any workspace (e.g. provisioned by
        // a seed) gets their Personal default before first render.
        $this->ensureDefault->__invoke($userId);

        return $this->workspaces->listForUser($userId, $archived);
    }
}
