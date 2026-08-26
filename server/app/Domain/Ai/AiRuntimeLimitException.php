<?php

namespace App\Domain\Ai;

use RuntimeException;

/**
 * TASK-P25-007 — hard runtime safeguard denial (429). Separated from the
 * entitlement denial (403 ENTITLEMENT_LIMIT): credits protect economics,
 * these protect the runtime. Applies to hosted AND BYOK requests.
 */
final class AiRuntimeLimitException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context  safe limit/usage context
     */
    public function __construct(
        string $message,
        public readonly string $runtimeCode,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
