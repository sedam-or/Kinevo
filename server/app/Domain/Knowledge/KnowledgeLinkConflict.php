<?php

namespace App\Domain\Knowledge;

use Exception;

/**
 * Raised when an identical knowledge link already exists for the user.
 * Maps to a 409 conflict; the unique database constraint backs this up.
 */
final class KnowledgeLinkConflict extends Exception
{
    public static function duplicate(): self
    {
        return new self('A knowledge link with the same source, target and type already exists.');
    }
}
