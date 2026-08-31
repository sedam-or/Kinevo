<?php

namespace App\Application\Scheduling;

use App\Application\Observability\RecordSchedulerRunUseCase;
use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Scheduling\Contracts\ScheduleReviewRepository;
use App\Domain\Scheduling\DynamicRescheduler;
use App\Domain\Scheduling\RescheduleProposal;
use App\Domain\Scheduling\ScheduleState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * ADR-016 §2.2 — manual Sync Now: load the current Effective Landscape,
 * compare against accepted placements with the SAME deterministic rescheduler
 * diff, and return a previewable state. Never force-writes, never calls AI,
 * never re-imports, never discards locked work. Apply stays the existing
 * explicit reschedule-apply flow (409 on stale base).
 *
 * Run lock: one concurrent sync per user (cache lock — same facility as the
 * AI generate throttle). Contention is deterministic: `run_in_progress`.
 */
final readonly class SyncNowUseCase
{
    private const LOCK_TTL_SECONDS = 60;

    public function __construct(
        private AssembleScheduleInput $assemble,
        private DynamicRescheduler $rescheduler,
        private ScheduleReviewRepository $reviews,
        private ProfileRepository $profiles,
        private RecordSchedulerRunUseCase $recordRun,
    ) {}

    public function __invoke(int $userId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): SyncNowResult
    {
        $needsReview = $this->reviews->findForUser($userId)->needsReview;
        $lock = Cache::lock('schedule:sync:'.$userId, self::LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            return SyncNowResult::runInProgress($needsReview);
        }

        $startedAtMs = (int) (microtime(true) * 1000);

        try {
            [$horizonFrom, $horizonTo] = $this->horizon($userId, $from, $to);
            $assembled = ($this->assemble)($userId, $horizonFrom, $horizonTo);
            $state = new ScheduleState($assembled['base_version'], $assembled['slots_by_task']);
            $proposal = $this->rescheduler->propose($state, $assembled['input']);

            $result = $this->toResult($proposal, $needsReview);

            if ($result->status === SyncNowResult::NO_CHANGES) {
                // Explicit acknowledgement: reality is already reflected in
                // the accepted schedule — clear the review flag (ADR-016 §2.3).
                $this->reviews->markReviewed($userId, $assembled['base_version']->value);
            }

            $this->recordRun->success($userId, 'schedule:sync', $this->durationMs($startedAtMs));

            return $result;
        } catch (Throwable $error) {
            $this->recordRun->failed($userId, 'schedule:sync', $this->durationMs($startedAtMs), $error->getMessage());

            throw $error;
        } finally {
            $lock->release();
        }
    }

    /**
     * ADR-016 §2.2 — bounded horizon: client range (≤ 14 days) or the
     * profile-local current Monday–Sunday week.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function horizon(int $userId, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        if ($from !== null && $to !== null) {
            if ($to->startOfDay()->lt($from->startOfDay()) || $from->startOfDay()->diffInDays($to->startOfDay()) > 13) {
                throw new \InvalidArgumentException('Sync horizon must be a range of at most 14 days.');
            }

            return [$from->startOfDay(), $to->endOfDay()];
        }

        $timezone = $this->profiles->findForUser($userId)?->settings->timezone ?? config('app.timezone');
        $weekStart = CarbonImmutable::now($timezone)->startOfWeek();

        return [$weekStart, $weekStart->addDays(6)->endOfDay()];
    }

    private function toResult(RescheduleProposal $proposal, bool $needsReview): SyncNowResult
    {
        if (! $proposal->hasChanges() && $proposal->conflictTaskIds === []) {
            return new SyncNowResult(SyncNowResult::NO_CHANGES, $proposal->baseVersion, $needsReview);
        }

        return new SyncNowResult(
            SyncNowResult::PROPOSAL,
            $proposal->baseVersion,
            $needsReview,
            $proposal->moves,
            array_values($proposal->conflictTaskIds),
            $proposal->newVersion->value,
        );
    }

    private function durationMs(int $startedAtMs): int
    {
        return max(0, ((int) (microtime(true) * 1000)) - $startedAtMs);
    }
}
