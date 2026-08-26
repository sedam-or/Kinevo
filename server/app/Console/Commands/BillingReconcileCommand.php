<?php

namespace App\Console\Commands;

use App\Infrastructure\Billing\MidtransGateway;
use App\Models\BillingSubscription;
use App\Models\SaasSubscription;
use Illuminate\Console\Command;

/**
 * TASK-P24-017/037 — billing reconciliation and operations diagnostics.
 * Dry-run first; repairs are auditable and never silently mass-overwrite.
 */
final class BillingReconcileCommand extends Command
{
    protected $signature = 'billing:reconcile
        {--dry-run : Report without mutating}
        {--user= : Scope to one user id}';

    protected $description = 'Detect and repair uncertain/mismatched billing subscriptions';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;

        $query = BillingSubscription::query()
            ->where('uncertain', true)
            ->orWhereNull('provider_subscription_id')
            ->where('state', '!=', 'pending');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $uncertain = $query->orderByDesc('updated_at')->limit(100)->get();

        if ($uncertain->isEmpty()) {
            $this->info('No uncertain subscriptions found.');

            return self::SUCCESS;
        }

        foreach ($uncertain as $sub) {
            $label = "#{$sub->id} user={$sub->user_id} plan={$sub->plan_code} state={$sub->state} provider_sub={$sub->provider_subscription_id} uncertain={$sub->uncertain}";

            if ($dryRun) {
                $this->line("[DRY-RUN] would review: {$label}");

                continue;
            }

            // Attempt live lookup via gateway to resolve.
            try {
                $gateway = app(MidtransGateway::class);
                $remote = $gateway->getSubscription((string) $sub->provider_subscription_id);
                $remoteStatus = strtolower((string) ($remote['status'] ?? ''));
                $mapped = $gateway->mapStatus($remoteStatus);

                $sub->state = $mapped->value;
                $sub->uncertain = false;
                $sub->save();

                // Sync P23 entitlement resolver.
                $saas = SaasSubscription::query()->where('user_id', $sub->user_id)->first();
                if ($saas === null) {
                    SaasSubscription::query()->create([
                        'user_id' => $sub->user_id,
                        'plan_code' => $sub->plan_code,
                        'provider' => 'midtrans',
                        'state' => 'active',
                    ]);
                } else {
                    $saas->plan_code = $sub->plan_code;
                    $saas->state = 'active';
                    $saas->save();
                }

                $this->info("RESOLVED: {$label} → {$mapped->value}");
            } catch (\Throwable $e) {
                $this->warn("UNRESOLVED: {$label} — {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
