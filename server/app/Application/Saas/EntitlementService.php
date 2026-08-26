<?php

namespace App\Application\Saas;

use App\Domain\Saas\Contracts\SubscriptionRepository;
use App\Domain\Saas\Contracts\UsageRepository;
use App\Domain\Saas\Exceptions\EntitlementLimitException;
use App\Domain\Saas\Plan;
use App\Domain\Saas\Subscription;
use Carbon\CarbonImmutable;

/**
 * TASK-P23-004 — THE centralized entitlement authority. No code outside this
 * service may branch on plan codes (§7 business-model rule).
 *
 * Semantics:
 * - effective subscription = persisted row when active, else default plan;
 * - `can`  → boolean capability check;
 * - `limit`→ numeric allowance for limit-style keys;
 * - `consume`→ atomically reserve one unit AFTER any preflight `remaining`
 *   check by the caller; period = calendar month (UTC).
 */
final class EntitlementService
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private UsageRepository $usage,
    ) {}

    public function subscriptionFor(int $userId): Subscription
    {
        return $this->subscriptions->forUser($userId) ?? Subscription::default($userId);
    }

    /** Effective plan for the user (non-active subscriptions degrade to free). */
    public function planFor(int $userId): Plan
    {
        return Plan::fromConfig($this->subscriptionFor($userId)->effectivePlanCode());
    }

    public function can(int $userId, string $entitlement): bool
    {
        return (bool) $this->planFor($userId)->entitlement($entitlement);
    }

    public function limit(int $userId, string $entitlement): int
    {
        return (int) ($this->planFor($userId)->entitlement($entitlement) ?? 0);
    }

    public function periodFor(?CarbonImmutable $now = null): string
    {
        return ($now ?? CarbonImmutable::now())->format('Y-m');
    }

    public function used(int $userId, string $key): int
    {
        return $this->usage->forPeriod($userId, $key, $this->periodFor())->consumed;
    }

    public function remaining(int $userId, string $key): int
    {
        return max(0, $this->limit($userId, $key) - $this->used($userId, $key));
    }

    /**
     * Atomically consume one unit of a metered allowance. Callers MUST have
     * checked `remaining > 0` first; the counter increment is atomic but the
     * over-limit guard is the caller's preflight responsibility.
     */
    public function consume(int $userId, string $key): void
    {
        $this->usage->increment($userId, $key, $this->periodFor());
    }

    /**
     * Guard helper for limit-style keys (e.g. max_workspaces). Throws a
     * UI-usable denial carrying safe context for the upgrade UX.
     */
    public function assertWithinLimit(int $userId, string $key, int $currentCount): void
    {
        $plan = $this->planFor($userId);
        $limit = (int) ($plan->entitlement($key) ?? 0);
        if ($currentCount >= $limit) {
            throw new EntitlementLimitException(
                "Your {$plan->name} plan allows {$limit} workspaces. Upgrade to create more.",
                $key,
                $plan->code,
                ['limit' => $limit, 'used' => $currentCount],
            );
        }
    }

    /** Guard helper for boolean capability keys. */
    public function assertCan(int $userId, string $key, string $message): void
    {
        if (! $this->can($userId, $key)) {
            $plan = $this->planFor($userId);
            throw new EntitlementLimitException($message, $key, $plan->code);
        }
    }
}
