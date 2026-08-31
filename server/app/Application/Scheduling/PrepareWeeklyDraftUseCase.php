<?php

namespace App\Application\Scheduling;

use App\Domain\Identity\Contracts\ProfileRepository;
use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\Notification;
use App\Domain\Notifications\ValueObjects\NotificationType;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleDraftRepository;
use App\Domain\Scheduling\ScheduleDraft;
use App\Domain\Scheduling\ScheduleDraftGenerator;
use App\Domain\Scheduling\ScheduleDraftRecord;
use App\Domain\Scheduling\ValueObjects\ScheduleDraftStatus;
use Carbon\CarbonImmutable;

/**
 * ADR-016 §2.1 — weekly planning trigger (per user). Computes a deterministic
 * planning draft for the user's LOCAL Monday–Sunday week and persists it as a
 * pending draft. NEVER applies anything; accepted placements only occupy slots
 * in the computation. The user reviews and applies through the existing flow.
 *
 * Idempotency (weekly dedup, one row per user/week):
 *  - no draft for this week → create + notify `weekly_draft_ready`;
 *  - pending and fresh → skip;
 *  - pending and stale → refresh in place (payload + base_version) + notify;
 *  - applied/discarded → skip (the user already handled this week).
 * Older pending weekly drafts are superseded (housekeeping).
 */
final readonly class PrepareWeeklyDraftUseCase
{
    public function __construct(
        private AssembleScheduleInput $assemble,
        private ScheduleDraftGenerator $generator,
        private ScheduleDraftRepository $drafts,
        private ScheduleAssignmentRepository $assignments,
        private ProfileRepository $profiles,
        private NotificationRepository $notifications,
    ) {}

    public function __invoke(int $userId, ?CarbonImmutable $localDay = null): PrepareWeeklyDraftResult
    {
        $timezone = $this->profiles->findForUser($userId)?->settings->timezone ?? config('app.timezone');
        $today = $localDay ?? CarbonImmutable::now($timezone)->startOfDay();
        $weekAnchor = $today->startOfWeek();

        // Housekeeping: pending weekly drafts from earlier weeks are superseded.
        foreach ($this->drafts->listPendingWeeklyForUser($userId) as $pending) {
            if ($pending->generatedForWeek !== null && $pending->generatedForWeek->lt($weekAnchor)) {
                $this->drafts->updateStatus($userId, (int) $pending->id, ScheduleDraftStatus::superseded());
            }
        }

        $existing = $this->drafts->findWeeklyForWeek($userId, $weekAnchor);

        if ($existing !== null && ! $existing->isPending()) {
            return PrepareWeeklyDraftResult::skipped($existing);
        }

        if ($existing !== null && ! $existing->isStale($this->currentVersion($userId))) {
            return PrepareWeeklyDraftResult::skipped($existing);
        }

        $from = $weekAnchor;
        $to = $weekAnchor->addDays(6);
        $assembled = ($this->assemble)($userId, $from, $to);
        $draft = $this->generator->generate($assembled['input']);

        // ADR-016 §2.1 — no schedulable work → no draft, no notification.
        if ($draft->assignments === [] && $draft->unassigned === []) {
            return PrepareWeeklyDraftResult::skipped(null);
        }

        $payload = $this->payload($draft, $assembled['base_version']->value);

        if ($existing === null) {
            $record = $this->drafts->create(ScheduleDraftRecord::weekly(
                $userId,
                $payload,
                $assembled['base_version']->value,
                $from,
                $to,
                $weekAnchor,
            ));

            $this->notify($userId, $weekAnchor);

            return PrepareWeeklyDraftResult::created($record);
        }

        $record = $this->drafts->refreshWeekly(
            $userId,
            (int) $existing->id,
            $payload,
            $assembled['base_version']->value,
            $from,
            $to,
        );

        $this->notify($userId, $weekAnchor);

        return PrepareWeeklyDraftResult::refreshed($record);
    }

    private function currentVersion(int $userId): int
    {
        return $this->assignments->currentScheduleVersion($userId)->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ScheduleDraft $draft, int $baseVersion): array
    {
        return [
            'draft' => [
                'assignments' => array_map(
                    static fn ($assignment) => [
                        'task_id' => $assignment->taskId,
                        'title' => $assignment->title,
                        'start' => $assignment->slot->start->toISOString(),
                        'end' => $assignment->slot->end->toISOString(),
                    ],
                    $draft->assignments,
                ),
                'unassigned' => array_map(
                    static fn ($task) => [
                        'task_id' => $task->taskId,
                        'title' => $task->title,
                        'reason' => $task->reason,
                    ],
                    $draft->unassigned,
                ),
            ],
            'base_version' => $baseVersion,
        ];
    }

    private function notify(int $userId, CarbonImmutable $weekAnchor): void
    {
        $type = NotificationType::weeklyDraftReady();

        if ($this->notifications->findForDay($userId, $type, $weekAnchor) !== null) {
            return;
        }

        $this->notifications->create(Notification::create(
            $userId,
            $type,
            $weekAnchor,
            'Your weekly plan draft is ready.',
            ['week_start' => $weekAnchor->toDateString()],
        ));
    }
}
