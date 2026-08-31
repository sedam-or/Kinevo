<?php

namespace App\Console\Commands;

use App\Domain\OfflineSync\Contracts\OfflineOperationRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * ADR-017 §2.5 — prune the offline operation ledger beyond the retention
 * horizon. Rows older than `offline.ledger_retention_days` are deleted; a
 * replay of an expired operation is rejected (`expired`) and the client
 * regenerates its operation_id.
 */
final class PruneOfflineLedgerCommand extends Command
{
    protected $signature = 'offline:prune-ledger';

    protected $description = 'Delete offline operation ledger rows older than the retention horizon (ADR-017)';

    public function __construct(
        private readonly OfflineOperationRepository $ledger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) config('offline.ledger_retention_days', 90);
        $before = CarbonImmutable::now()->subDays($days);
        $pruned = $this->ledger->pruneOlderThan($before);

        $this->info("offline:prune-ledger — pruned {$pruned} rows older than {$days} days.");

        return self::SUCCESS;
    }
}
