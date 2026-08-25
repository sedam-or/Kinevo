<?php

namespace App\Domain\Workspaces\Contracts;

use App\Domain\Workspaces\Exceptions\DuplicateWorkspaceSlugException;
use App\Domain\Workspaces\Workspace;

interface WorkspaceRepository
{
    public function findForUser(int $userId, int $workspaceId): ?Workspace;

    /**
     * @return array<int, Workspace>
     */
    public function listForUser(int $userId, ?bool $archived = null): array;

    public function findBySlug(int $userId, string $slug): ?Workspace;

    public function defaultForUser(int $userId): ?Workspace;

    /**
     * Persists a new workspace. Implementations MUST guarantee slug
     * uniqueness per user (suffixing when needed) and that saving a default
     * clears the previous default atomically.
     */
    public function create(Workspace $workspace): Workspace;

    /**
     * @throws DuplicateWorkspaceSlugException
     */
    public function update(Workspace $workspace): Workspace;

    /** Exactly-one-default invariant; runs in a transaction. */
    public function setDefault(int $userId, int $workspaceId): void;

    /**
     * TASK-P19-003 lazy adoption — assigns every workspace-aware row of the
     * owner that has no workspace yet to the given workspace. Used when a
     * default is provisioned after data already exists (users seeded outside
     * the registration path). Idempotent by construction (NULL-guard).
     */
    public function adoptUnassigned(int $userId, int $workspaceId): void;
}
