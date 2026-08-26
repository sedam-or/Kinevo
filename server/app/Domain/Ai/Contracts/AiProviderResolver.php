<?php

namespace App\Domain\Ai\Contracts;

/**
 * Resolves the AI provider for a request (SRS FR-60). Resolution is
 * user-scoped since P25-008: an enabled per-user BYOK credential wins over the
 * global (Kinevo-hosted) configuration. Implementations MUST NOT cache across
 * users. `null` userId = non-user/system path (no BYOK, global default).
 */
interface AiProviderResolver
{
    public function resolve(int $userId): AiProvider;

    /**
     * Whether this user's request will run on their own (BYOK) credential —
     * used by the billing split (no ai_credits, billable_to_kinevo=false).
     */
    public function isUserProvided(int $userId): bool;
}
