<?php

namespace App\Application\Workspaces;

use App\Domain\Workspaces\Contracts\WorkspaceRepository;
use App\Domain\Workspaces\Workspace;
use InvalidArgumentException;

final readonly class UpdateWorkspaceUseCase
{
    public function __construct(
        private WorkspaceRepository $workspaces,
    ) {}

    /**
     * PATCH semantics: omitted fields keep their stored values.
     *
     * @param  array<string, mixed>  $input
     */
    public function __invoke(int $userId, int $workspaceId, array $input): Workspace
    {
        $existing = $this->workspaces->findForUser($userId, $workspaceId);
        if ($existing === null) {
            throw new InvalidArgumentException('Workspace not found.');
        }

        $updated = $existing;
        if (array_key_exists('name', $input)) {
            $updated = $updated->rename((string) $input['name']);
        }
        if (array_key_exists('description', $input)) {
            $updated = $updated->describe(isset($input['description']) ? (string) $input['description'] : null);
        }
        if (array_key_exists('icon', $input) || array_key_exists('accent', $input)) {
            // PATCH: only fields actually sent may change/clear.
            $updated = $updated->restyle(
                icon: array_key_exists('icon', $input)
                    ? (isset($input['icon']) ? (string) $input['icon'] : null)
                    : $existing->icon,
                accent: array_key_exists('accent', $input)
                    ? (isset($input['accent']) ? (string) $input['accent'] : null)
                    : $existing->accent,
            );
        }

        return $this->workspaces->update($updated);
    }
}
