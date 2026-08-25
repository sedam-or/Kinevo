<?php

namespace App\Application\Workspaces;

use App\Domain\Workspaces\Contracts\WorkspaceRepository;
use App\Domain\Workspaces\Workspace;

/**
 * Guarantees every user has exactly one default "Personal" workspace
 * (TASK-P19-003): invoked at registration and as a lazy safety net for
 * users provisioned outside the registration path (dev seeds, factories).
 */
final readonly class EnsureDefaultWorkspaceUseCase
{
    public function __construct(
        private WorkspaceRepository $workspaces,
    ) {}

    public function __invoke(int $userId): Workspace
    {
        $existing = $this->workspaces->defaultForUser($userId);
        if ($existing !== null) {
            return $existing;
        }

        $default = $this->workspaces->create(Workspace::defaultFor(userId: $userId));
        // TASK-P19-003 — data created before the default existed (users
        // seeded outside registration) is adopted into Personal. Idempotent:
        // only NULL-workspace rows are touched, never reassigned ones.
        $this->workspaces->adoptUnassigned($userId, $default->id);

        return $default;
    }
}
