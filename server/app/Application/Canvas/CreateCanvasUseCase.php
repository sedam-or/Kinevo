<?php

namespace App\Application\Canvas;

use App\Domain\Canvas\Canvas;
use App\Domain\Canvas\Contracts\CanvasRepository;

final class CreateCanvasUseCase
{
    public function __construct(
        private readonly CanvasRepository $repository,
    ) {}

    public function __invoke(
        int $userId,
        string $title,
        ?int $goalId = null,
        ?int $milestoneId = null,
        ?int $programId = null,
        ?int $taskId = null,
        ?int $workspaceId = null,
    ): Canvas {
        $canvas = Canvas::create(
            $userId,
            $title,
            $goalId,
            $milestoneId,
            $programId,
            $taskId,
        );

        if ($workspaceId !== null) {
            $canvas = $canvas->withWorkspace($workspaceId);
        }

        return $this->repository->create($userId, $canvas);
    }
}
