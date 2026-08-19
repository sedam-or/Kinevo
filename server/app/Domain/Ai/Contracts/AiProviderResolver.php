<?php

namespace App\Domain\Ai\Contracts;

use InvalidArgumentException;

/**
 * Resolves the configured AI provider (SRS FR-60). Implementations MAY cache
 * the resolved provider; resolution happens at call time so runtime-selected
 * configuration (e.g. per-test overrides) is honored.
 */
interface AiProviderResolver
{
    /**
     * @throws InvalidArgumentException when the configured driver is unknown
     */
    public function resolve(): AiProvider;
}
