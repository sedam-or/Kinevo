<?php

namespace App\Domain\Knowledge;

use RuntimeException;

/**
 * Raised when a stale note update is attempted (optimistic version conflict).
 * Maps to HTTP 409 at the boundary.
 */
final class NoteVersionConflict extends RuntimeException
{
    public function __construct(int $expectedVersion, int $actualVersion)
    {
        parent::__construct(
            "Note version conflict: expected {$expectedVersion}, got {$actualVersion}."
        );
    }
}
