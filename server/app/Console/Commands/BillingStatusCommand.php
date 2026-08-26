<?php

namespace App\Console\Commands;

use App\Models\BillingSubscription;
use App\Models\BillingTransaction;
use Illuminate\Console\Command;

/** TASK-P24-037 — operator-safe billing diagnostics (no card data, no secrets). */
final class BillingStatusCommand extends Command
{
    protected $signature = 'billing:status {--user= : Filter by user id}';

    protected $description = 'Show billing subscription and transaction summary';

    public function handle(): int
    {
        $query = BillingSubscription::query()->orderByDesc('id')->limit(50);
        if ($this->option('user') !== null) {
            $query->where('user_id', (int) $this->option('user'));
        }
        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('No billing subscriptions found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'User', 'Plan', 'State', 'Provider Sub ID', 'Uncertain'],
            $rows->map(fn ($r) => [$r->id, $r->user_id, $r->plan_code, $r->state, $r->provider_subscription_id ?? '—', $r->uncertain ? '⚠' : '']),
        );

        $txQuery = BillingTransaction::query()->orderByDesc('id')->limit(20);
        if ($this->option('user') !== null) {
            $txQuery->where('user_id', (int) $this->option('user'));
        }
        $txs = $txQuery->get();
        if ($txs->isNotEmpty()) {
            $this->table(
                ['Tx ID', 'Sub', 'Amount', 'Currency', 'Status'],
                $txs->map(fn ($t) => [$t->provider_transaction_id, $t->billing_subscription_id, number_format($t->amount_minor / 100), $t->currency, $t->status]),
            );
        }

        return self::SUCCESS;
    }
}
