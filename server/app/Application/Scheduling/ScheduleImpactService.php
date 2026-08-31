<?php

namespace App\Application\Scheduling;

use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\Notification;
use App\Domain\Notifications\ValueObjects\NotificationType;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\Contracts\ScheduleReviewRepository;
use App\Domain\Scheduling\Resolution\EffectiveLandscapeResolver;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ADR-016 §2.3 — bounded, lazy reality-change impact detection.
 *
 * After an authoritative reality mutation commits, the owning use case calls
 * this service with the mutation's window. The service:
 *  - clamps the window to [today−7, today+14] (profile-local) — changes
 *    outside the planning horizon never trigger work;
 *  - resolves the Effective Landscape (ADR-015) for that window;
 *  - flags `needs_review` when an accepted auto-sourced placement now
 *    overlaps an effective occurrence;
 *  - notifies at most once per false→true transition per local day.
 *
 * NEVER auto-applies anything and NEVER fails the authoritative mutation:
 * the whole body is failure-isolated.
 */
final readonly class ScheduleImpactService
{
    private const WINDOW_BACKWARD_DAYS = 7;

    private const WINDOW_FORWARD_DAYS = 14;

    public function __construct(
        private EffectiveLandscapeResolver $landscapeResolver,
        private HardLandscapeRepository $hardLandscape,
        private ScheduleOverrideRepository $overrides,
        private ScheduleAssignmentRepository $assignments,
        private ScheduleReviewRepository $reviews,
        private NotificationRepository $notifications,
        private ProfileRepository $profiles,
    ) {}

    /**
     * @param  array<int, int|string>  $reasonIds
     */
    public function assess(
        int $userId,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        string $reason,
        array $reasonIds = [],
    ): bool {
        try {
            return $this->doAssess($userId, $windowStart, $windowEnd, $reason, $reasonIds);
        } catch (Throwable $error) {
            // Failure isolation (ADR-016 §2.8): the authoritative mutation has
            // already committed; impact detection must never break it.
            Log::warning('Schedule impact detection failed', [
                'user_id' => $userId,
                'reason' => $reason,
                'error' => $error->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<int, int|string>  $reasonIds
     */
    private function doAssess(
        int $userId,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        string $reason,
        array $reasonIds,
    ): bool {
        $timezone = $this->profiles->findForUser($userId)?->settings->timezone ?? config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $boundStart = $today->subDays(self::WINDOW_BACKWARD_DAYS);
        $boundEnd = $today->addDays(self::WINDOW_FORWARD_DAYS)->endOfDay();

        $start = $windowStart->startOfDay()->max($boundStart);
        $end = $windowEnd->endOfDay()->min($boundEnd);

        if ($start->gt($end)) {
            // Outside the bounded planning horizon — no work, no state change.
            return false;
        }

        $resolution = $this->landscapeResolver->resolve(
            $this->hardLandscape->listForUser($userId),
            $this->overrides->listForUser($userId),
            $start,
            $end,
        );

        if ($resolution->occurrences === []) {
            return false;
        }

        $autoSources = [
            ScheduleAssignmentSource::DRAFT,
            ScheduleAssignmentSource::RESCHEDULE,
            ScheduleAssignmentSource::QUICK_CAPTURE,
        ];

        foreach ($this->assignments->listForUserInRange($userId, $start, $end) as $placement) {
            if (! in_array($placement->source->value, $autoSources, true)) {
                continue;
            }

            foreach ($resolution->occurrences as $occurrence) {
                if ($occurrence->timeRange()->overlaps($placement->timeRange())) {
                    $this->flag($userId, $reason, $reasonIds, $today);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, int|string>  $reasonIds
     */
    private function flag(int $userId, string $reason, array $reasonIds, CarbonImmutable $localToday): void
    {
        $previous = $this->reviews->findForUser($userId);
        $version = $this->assignments->currentScheduleVersion($userId)->value;
        $this->reviews->markNeedsReview($userId, [$reason => array_values($reasonIds)], $version);

        if ($previous->needsReview) {
            // Already flagged — no notification spam (ADR-016 §2.9).
            return;
        }

        $type = NotificationType::scheduleNeedsReview();
        if ($this->notifications->findForDay($userId, $type, $localToday) !== null) {
            return;
        }

        $this->notifications->create(Notification::create(
            $userId,
            $type,
            $localToday,
            'Your schedule may be impacted by a change.',
            ['reason' => $reason],
        ));
    }
}
