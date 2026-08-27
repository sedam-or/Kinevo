<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * COMMERCIAL PRICING DELTA D-006 / P32-006 — unit economics per plan from the
 * config grid (billing.payment_fees + billing.unit_economics). Inputs are
 * DEPRECATED-BASELINE assumptions until real measurement; output is a planning
 * aid, not an invoice. Scenario shares model AI COGS as a fraction of revenue.
 */
final class BillingUnitEconomicsCommand extends Command
{
    protected $signature = 'billing:unit-economics
        {--json : Emit machine-readable JSON}
        {--save= : Write report (default storage/app/private/unit-economics.json)}';

    protected $description = 'Plan-level unit economics from configured fees, COGS shares, infra/support (D-006)';

    public function handle(): int
    {
        $prices = (array) config('billing.prices');
        $fees = (array) config('billing.payment_fees');
        $model = (array) config('billing.unit_economics');
        $defaultMethod = (string) config('billing.default_payment_method', 'credit_card');
        $fee = (array) ($fees[$defaultMethod] ?? ['fixed_minor' => 0, 'percentage_bps' => 0]);

        $rows = [];
        foreach ((array) $model['scenarios'] as $plan => $scenarios) {
            $revenue = (int) ($plan === 'free' ? 0 : ($prices[$plan]['amount_minor'] ?? 0));
            foreach ($scenarios as $scenario => $share) {
                $aiCogs = (int) round($revenue * (float) $share);
                $paymentFee = $revenue > 0 ? (int) $fee['fixed_minor'] + (int) round($revenue * (int) $fee['percentage_bps'] / 10000) : 0;
                $infra = (int) $model['infra_minor_per_user_month'];
                $storage = (int) $model['storage_minor_per_user_month'];
                $support = (int) $model['support_minor_per_user_month'];
                $costs = $paymentFee + $aiCogs + $infra + $storage + $support;
                $contribution = $revenue - $costs;
                $rows[$plan][$scenario] = [
                    'revenue_minor' => $revenue,
                    'payment_fee_minor' => $paymentFee,
                    'ai_cogs_minor' => $aiCogs,
                    'infra_minor' => $infra,
                    'storage_minor' => $storage,
                    'support_minor' => $support,
                    'total_cost_minor' => $costs,
                    'gross_contribution_minor' => $contribution,
                    'margin' => $revenue > 0 ? round($contribution / $revenue, 4) : null,
                ];
            }
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'payment_method' => $defaultMethod,
            'payment_fee_assumptions' => $fee,
            'assumptions_note' => (string) ($model['note'] ?? ''),
            'plans' => $rows,
            'beta_pricing_metrics' => [
                'definitions' => [
                    'pricing_page_view', 'upgrade_cta_click', 'checkout_start', 'checkout_completion',
                    'first_paid_action', 'd7_retention', 'd30_retention', 'cancellation', 'downgrade',
                    'power_selection_rate', 'ai_cogs_per_paid_user', 'pro_to_power_upgrade_rate',
                    'qualitative_power_reason',
                ],
                'note' => 'Instrumentation lands with P32-001 taxonomy/P37 metrics; definitions recorded here now.',
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $tableRows = [];
        foreach ($report['plans'] as $plan => $scenarios) {
            foreach ($scenarios as $scenario => $r) {
                $tableRows[] = [
                    $plan, $scenario, $r['revenue_minor'], $r['payment_fee_minor'], $r['ai_cogs_minor'],
                    $r['infra_minor'], $r['total_cost_minor'], $r['gross_contribution_minor'],
                    $r['margin'] === null ? '-' : sprintf('%.1f%%', $r['margin'] * 100),
                ];
            }
        }
        $this->table(
            ['Plan', 'Scenario', 'Revenue', 'PayFee', 'AI COGS', 'Infra', 'Costs', 'Contrib', 'Margin'],
            $tableRows,
        );

        $savePath = $this->option('save') === null ? 'private/unit-economics.json' : (string) $this->option('save');
        Storage::put($savePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Report saved to storage: {$savePath}");

        return self::SUCCESS;
    }
}
