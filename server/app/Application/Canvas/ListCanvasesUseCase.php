<?php

namespace App\Application\Canvas;

use App\Domain\Canvas\Canvas;
use App\Domain\Canvas\Contracts\CanvasRepository;

final class ListCanvasesUseCase
{
    public function __construct(
        private readonly CanvasRepository $repository,
    ) {}

    /**
     * @return array<int, Canvas>
     */
    public function __invoke(int $userId, ?int $workspaceId = null): array
    {
        // TASK-P19-017 — workspace filter; null = global view.
        if ($workspaceId !== null) {
            return $this->repository->listForUserInWorkspace($userId, $workspaceId);
        }

        return $this->repository->listForUser($userId);
    }
}
