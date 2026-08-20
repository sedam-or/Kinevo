<?php

namespace App\Application\Breaks;

use App\Domain\Breaks\Contracts\BreakPeriodRepository;
use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\Notification;
use App\Domain\Notifications\ValueObjects\NotificationType;
use Carbon\CarbonImmutable;

/**
 * Holiday-end notification scan (FR-39/FR-41). Creates exactly ONE in-app
 * `break_end` notification per break period, three days before the break ends
 * (H-3, user timezone), carrying the break summary report. Idempotent: a retry
 * never creates a duplicate (payload-keyed lookup, FR-39 Exception Flows).
 *
 * The notification is informational; it does not end the break itself.
 */
final readonly class RunBreakEndNotificationUseCase
{
    public function __construct(
        private BreakPeriodRepository $breaks,
        private NotificationRepository $notifications,
    ) {}

    /**
     * @return array<int, Notification> created notifications
     */
    public function __invoke(int $userId, CarbonImmutable $today): array
    {
        $h3 = $today->addDays(3);
        $due = array_values(array_filter(
            $this->breaks->listActiveEndingOnOrAfter($userId, $h3),
            static fn ($period) => $period->endDate->isSameDay($h3),
        ));

        $created = [];

        foreach ($due as $period) {
            if ($this->notifications->findBreakEndForPeriod($userId, $period->id) !== null) {
                continue;
            }

            $created[] = $this->notifications->create(Notification::create(
                $userId,
                NotificationType::breakEnd(),
                $today,
                'Break Mode ends in 3 days',
                [
                    'break_period_id' => (string) $period->id,
                    'start_date' => $period->startDate->toDateString(),
                    'end_date' => $period->endDate->toDateString(),
                    'duration_days' => $period->durationDays(),
                ],
            ));
        }

        return $created;
    }
}
