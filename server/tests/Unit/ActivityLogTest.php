<?php

namespace Tests\Unit;

use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ActivityLogTest extends TestCase
{
    #[Test]
    public function activity_log_can_be_created(): void
    {
        $log = ActivityLog::create(
            1,
            ActivityEventType::taskCompleted(),
            'task',
            42,
            'Write report',
        );

        $this->assertNull($log->id);
        $this->assertSame(1, $log->userId);
        $this->assertTrue($log->eventType->equals(ActivityEventType::taskCompleted()));
        $this->assertSame('task', $log->entityType);
        $this->assertSame(42, $log->entityId);
        $this->assertSame('Write report', $log->title);
        $this->assertInstanceOf(CarbonImmutable::class, $log->eventAt);
        $this->assertNull($log->operationId);
        $this->assertSame([], $log->payload);
    }

    #[Test]
    public function activity_log_with_id_and_payload_serializes(): void
    {
        $log = ActivityLog::create(
            1,
            ActivityEventType::subtaskCompleted(),
            'subtask',
            7,
            'Collect inputs',
            CarbonImmutable::parse('2026-08-18 09:00:00'),
            'op-123',
            ['task_id' => 42, 'task_title' => 'Build'],
        )->withId(5);

        $array = $log->toArray();

        $this->assertSame(5, $array['id']);
        $this->assertSame('subtask_completed', $array['event_type']);
        $this->assertSame('subtask', $array['entity_type']);
        $this->assertSame(7, $array['entity_id']);
        $this->assertSame('op-123', $array['operation_id']);
        $this->assertSame(['task_id' => 42, 'task_title' => 'Build'], $array['payload']);
        $this->assertSame('2026-08-18T09:00:00.000000Z', $array['event_at']);
    }

    #[Test]
    public function event_type_rejects_unknown_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ActivityEventType('bogus_event');
    }

    #[Test]
    public function event_type_factories_build_all_types(): void
    {
        $this->assertTrue(ActivityEventType::taskCompleted()->equals(ActivityEventType::taskCompleted()));
        $this->assertSame('task_continued', ActivityEventType::taskContinued()->value);
        $this->assertSame('subtask_completed', ActivityEventType::subtaskCompleted()->value);
        $this->assertFalse(ActivityEventType::taskCompleted()->equals(ActivityEventType::taskContinued()));
    }
}
