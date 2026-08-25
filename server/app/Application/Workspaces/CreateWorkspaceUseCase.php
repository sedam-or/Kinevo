<?php

namespace App\Application\Workspaces;

use App\Domain\Workspaces\Contracts\WorkspaceRepository;
use App\Domain\Workspaces\Exceptions\DuplicateWorkspaceSlugException;
use App\Domain\Workspaces\Workspace;

final readonly class CreateWorkspaceUseCase
{
    public function __construct(
        private WorkspaceRepository $workspaces,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws DuplicateWorkspaceSlugException when the slug is taken and no
     *                                         free suffix could be allocated (practically unreachable)
     */
    public function __invoke(int $userId, array $input): Workspace
    {
        $workspace = Workspace::create(
            userId: $userId,
            name: (string) ($input['name'] ?? ''),
            description: isset($input['description']) ? (string) $input['description'] : null,
            icon: isset($input['icon']) ? (string) $input['icon'] : null,
            accent: isset($input['accent']) ? (string) $input['accent'] : null,
            type: isset($input['type']) ? (string) $input['type'] : null,
        );

        return $this->workspaces->create($workspace);
    }
}
