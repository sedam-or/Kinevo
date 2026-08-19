<?php

namespace App\Console\Commands;

use App\Application\Reconciliation\RunEodDeadlineUseCase;
use App\Application\Reconciliation\RunEodPromptUseCase;
use App\Domain\Identity\Contracts\ProfileRepository;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * End-of-Day reconciliation job (FR-47, TASK-054).
 *
 * Scheduled at 21:00 (prompt) and 23:59 (deadline) local time via
 * bootstrap/app.php withSchedule. The job is idempotent: the prompt creates
 * exactly one reconciliation notification per user/day, and deadline
 * transitions are validated by the Task state machine, so retries never create
 * duplicate notifications or state transitions (FR-47 Exception Flows).
 */
final class EodReconcileCommand extends Command
{
    protected $signature = 'eod:reconcile {--phase=deadline : prompt (21:00) or deadline (23:59)}';

    protected $description = 'Scan tasks for end-of-day reconciliation (FR-47)';

    public function __construct(
        private readonly RunEodPromptUseCase $runPrompt,
        private readonly RunEodDeadlineUseCase $runDeadline,
        private readonly ProfileRepository $profiles,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $phase = $this->option('phase');

        foreach ($this->ownerIds() as $userId) {
            if ($phase === 'prompt') {
                $notification = $this->runPrompt->__invoke($userId, $this->localDay($userId));

                $this->info($notification === null
                    ? "EOD prompt (user {$userId}): no untouched tasks; no notification."
                    : "EOD prompt (user {$userId}): reconciliation notification #{$notification->id} created.");
            } else {
                $reconciled = $this->runDeadline->__invoke($userId);

                $this->info(sprintf('EOD deadline (user %d): %d task(s) marked missed (Terlewat).', $userId, count($reconciled)));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function ownerIds(): array
    {
        return User::query()->orderBy('id')->pluck('id')->all();
    }

    private function localDay(int $userId): CarbonImmutable
    {
        $timezone = $this->profiles->findForUser($userId)?->settings->timezone ?? config('app.timezone');

        return CarbonImmutable::now($timezone)->startOfDay();
    }
}
