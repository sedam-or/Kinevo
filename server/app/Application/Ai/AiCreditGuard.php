<?php

namespace App\Application\Ai;

use App\Application\Saas\EntitlementService;
use App\Domain\Saas\Exceptions\EntitlementLimitException;
use Illuminate\Support\Str;

/**
 * TASK-P25-003..005 — metered AI credit guard: preflight before any provider
 * call and postflight consumption on success. Called from the inference use
 * cases (not controllers) so every entry point bills identically and CLI
 * diagnostics can opt out. One unit is spent per successful inference;
 * failures and denials burn nothing.
 */
final readonly class AiCreditGuard
{
    public function __construct(
        private EntitlementService $entitlements,
    ) {}

    /**
     * Preflight: confirm a monthly credit remains, then issue the per-request
     * identity for the run. Denial throws the UI-usable entitlement error
     * (http 403 via the controller catch) before any provider cost accrues.
     */
    public function begin(int $userId): string
    {
        if ($this->entitlements->remaining($userId, 'ai_credits') <= 0) {
            $plan = $this->entitlements->planFor($userId);

            throw new EntitlementLimitException(
                "Monthly AI credits exhausted on the {$plan->name} plan.",
                'ai_credits',
                $plan->code,
                [
                    'limit' => 0,
                    'used' => $this->entitlements->used($userId, 'ai_credits'),
                ],
            );
        }

        return (string) Str::uuid();
    }

    /** Postflight: spend one credit for a successful generation. */
    public function spend(int $userId): void
    {
        $this->entitlements->consume($userId, 'ai_credits');
    }
}
