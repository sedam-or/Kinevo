<?php

namespace App\Domain\Saas\Exceptions;

use RuntimeException;

/** TASK-P23-007 — backend entitlement denial. Carries safe, UI-usable context. */
final class EntitlementLimitException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context  plan/limit/used/entitlement
     */
    public function __construct(
        string $message,
        public readonly string $entitlement,
        public readonly string $planCode,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /** @return array<string, mixed> */
    public function toResponse(): array
    {
        return [
            'error' => $this->getMessage(),
            'code' => 'ENTITLEMENT_LIMIT',
            'entitlement' => $this->entitlement,
            'plan' => $this->planCode,
            ...$this->context,
        ];
    }
}
