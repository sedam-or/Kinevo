<?php

namespace App\Domain\Workspaces\Exceptions;

use RuntimeException;

/**
 * A workspace slug collision within the same user scope. The API surfaces
 * this as 409 so the client can pick a different name.
 */
final class DuplicateWorkspaceSlugException extends RuntimeException {}
