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
    public function __invoke(int $userId): array
    {
        return $this->repository->listForUser($userId);
    }
}
