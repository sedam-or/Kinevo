<?php

namespace App\Console\Commands;

use App\Application\Breaks\RunBreakEndNotificationUseCase;
use App\Domain\Identity\Contracts\ProfileRepository;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Holiday-end notification job (FR-39/FR-41, TASK-124).
 *
 * Scheduled daily. For every user with an active break ending in exactly three
 * days, creates exactly one `break_end` notification with the break summary
 * report. Idempotent: retries never create a duplicate notification for the
 * same break period (FR-39 Exception Flows).
 *
 * Each run records scheduler telemetry with safe metadata only.
 */
final class BreakEndNotificationCommand extends Command
{
    protected $signature = 'break:notify-end';

    protected $description = 'Create H-3 holiday-end notifications for active breaks (FR-39)';

    public function __construct(
        private readonly RunBreakEndNotificationUseCase $runNotification,
        private readonly ProfileRepository $profiles,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $started = hrtime(true);

        try {
            foreach ($this->ownerIds() as $userId) {
                $created = $this->runNotification->__invoke($userId, $this->localDay($userId));

                $this->info(count($created) === 0
                    ? "Break-end check (user {$userId}): no break ending in 3 days; no notification."
                    : sprintf('Break-end check (user %d): %d break-end notification(s) created.', $userId, count($created)));
            }
        } catch (Throwable $e) {
            $this->error("Break-end check failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Break-end check completed in '.((int) ((hrtime(true) - $started) / 1_000_000)).'ms');

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
