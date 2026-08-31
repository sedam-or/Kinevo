<?php

namespace App\Console\Commands;

use App\Application\Observability\RecordSchedulerRunUseCase;
use App\Application\Scheduling\PrepareWeeklyDraftResult;
use App\Application\Scheduling\PrepareWeeklyDraftUseCase;
use App\Domain\Identity\Contracts\ProfileRepository;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * ADR-016 §2.1 — weekly planning trigger. Runs daily; prepares a persisted,
 * never-auto-applied planning draft for each user whose LOCAL date is Monday
 * (profile timezone decides the week). Per-user run lock, per-user failure
 * isolation, telemetry via scheduler_runs, safe retry (idempotent by week
 * anchor).
 */
final class PrepareWeeklyScheduleCommand extends Command
{
    protected $signature = 'schedule:prepare-weekly
        {--user= : Prepare for a single user id (debug/ops)}
        {--email= : Prepare for a single user email (debug/ops)}';

    protected $description = 'Prepare the weekly planning draft (review-ready, never auto-applied) for users whose local week just started';

    public function __construct(
        private readonly PrepareWeeklyDraftUseCase $prepare,
        private readonly ProfileRepository $profiles,
        private readonly RecordSchedulerRunUseCase $recordRun,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $startedAtMs = (int) (microtime(true) * 1000);
        $created = 0;
        $refreshed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->userIds() as $userId) {
            $localDay = $this->localDay($userId);

            if ($this->option('user') === null && $this->option('email') === null && ! $localDay->isMonday()) {
                continue;
            }

            $lock = Cache::lock('schedule:weekly:'.$userId, 60);

            if (! $lock->get()) {
                // ADR-016 §2.4 — contention (weekly racing a concurrent run)
                // deterministically skips this pass; tomorrow's pass retries.
                $skipped++;

                continue;
            }

            try {
                $result = ($this->prepare)($userId, $localDay);

                $created += $result->action === PrepareWeeklyDraftResult::CREATED ? 1 : 0;
                $refreshed += $result->action === PrepareWeeklyDraftResult::REFRESHED ? 1 : 0;
                $skipped += $result->action === PrepareWeeklyDraftResult::SKIPPED ? 1 : 0;
            } catch (Throwable $error) {
                $failed++;
                $this->recordRun->failed($userId, 'schedule:prepare-weekly', 0, $error->getMessage());
                $this->error("weekly prepare failed for user {$userId}: {$error->getMessage()}");
            } finally {
                $lock->release();
            }
        }

        $this->recordRun->success(null, 'schedule:prepare-weekly', $this->durationMs($startedAtMs));

        $this->info("schedule:prepare-weekly — created={$created} refreshed={$refreshed} skipped={$skipped} failed={$failed}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function userIds(): array
    {
        $userId = $this->option('user');
        if ($userId !== null) {
            return [(int) $userId];
        }

        $email = $this->option('email');
        if ($email !== null) {
            $id = User::query()->where('email', $email)->value('id');
            if ($id === null) {
                $this->error("no user with email {$email}");

                return [];
            }

            return [(int) $id];
        }

        return User::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function localDay(int $userId): CarbonImmutable
    {
        $timezone = $this->profiles->findForUser($userId)?->settings->timezone ?? config('app.timezone');

        return CarbonImmutable::now($timezone)->startOfDay();
    }

    private function durationMs(int $startedAtMs): int
    {
        return max(0, ((int) (microtime(true) * 1000)) - $startedAtMs);
    }
}
