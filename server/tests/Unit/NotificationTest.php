<?php

namespace Tests\Unit;

use App\Domain\Notifications\Notification;
use App\Domain\Notifications\ValueObjects\NotificationType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{
    #[Test]
    public function notification_can_be_created(): void
    {
        $notification = Notification::create(
            1,
            NotificationType::reconciliation(),
            CarbonImmutable::parse('2026-08-18'),
            'End-of-day reconciliation',
            ['task_id' => 42, 'title' => 'Write report', 'status' => 'scheduled'],
        );

        $this->assertNull($notification->id);
        $this->assertSame(1, $notification->userId);
        $this->assertTrue($notification->type->equals(NotificationType::reconciliation()));
        $this->assertSame('2026-08-18', $notification->scheduledFor?->toDateString());
        $this->assertSame('End-of-day reconciliation', $notification->title);
        $this->assertSame('scheduled', $notification->payload['status']);
        $this->assertNull($notification->readAt);
        $this->assertFalse($notification->isRead());
    }

    #[Test]
    public function notification_serializes_with_id_and_read_state(): void
    {
        $notification = Notification::create(
            1,
            NotificationType::reconciliation(),
            CarbonImmutable::parse('2026-08-18'),
            'End-of-day reconciliation',
            ['task_id' => 42],
        )->withId(5)->markRead(CarbonImmutable::parse('2026-08-18T21:00:00+07:00'));

        $array = $notification->toArray();

        $this->assertSame(5, $array['id']);
        $this->assertSame('reconciliation', $array['type']);
        $this->assertSame('2026-08-18', $array['scheduled_for']);
        $this->assertTrue($notification->isRead());
        $this->assertNotNull($array['read_at']);
    }

    #[Test]
    public function mark_read_is_idempotent(): void
    {
        $readAt = CarbonImmutable::parse('2026-08-18T21:00:00');
        $notification = Notification::create(1, NotificationType::reconciliation())->markRead($readAt);

        $twice = $notification->markRead(CarbonImmutable::parse('2026-08-18T22:00:00'));

        $this->assertTrue($twice->readAt?->equalTo($readAt));
    }

    #[Test]
    public function notification_type_rejects_unknown_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new NotificationType('bogus_notification');
    }
}
