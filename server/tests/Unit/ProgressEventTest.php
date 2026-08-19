<?php

namespace Tests\Unit;

use App\Domain\Progress\ProgressEvent;
use App\Domain\Progress\ValueObjects\ProgressEventType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProgressEventTest extends TestCase
{
    #[Test]
    public function type_is_a_closed_set(): void
    {
        $this->assertSame('task_completed', ProgressEventType::taskCompleted()->value);
        $this->assertSame('milestone_advanced', ProgressEventType::milestoneAdvanced()->value);
        $this->assertSame('milestone_completed', ProgressEventType::milestoneCompleted()->value);
        $this->assertSame('evidence_attached', ProgressEventType::evidenceAttached()->value);
        $this->assertSame('experiment_recorded', ProgressEventType::experimentRecorded()->value);
        $this->assertSame('goal_progress', ProgressEventType::goalProgress()->value);
    }

    #[Test]
    public function unknown_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProgressEventType('xp_earned');
    }

    #[Test]
    public function only_some_types_are_manually_recordable(): void
    {
        $this->assertTrue(ProgressEventType::evidenceAttached()->isManual());
        $this->assertTrue(ProgressEventType::experimentRecorded()->isManual());
        $this->assertTrue(ProgressEventType::goalProgress()->isManual());
        $this->assertFalse(ProgressEventType::taskCompleted()->isManual());
        $this->assertFalse(ProgressEventType::milestoneCompleted()->isManual());
    }

    #[Test]
    public function event_serializes_with_operation_reference(): void
    {
        $event = ProgressEvent::create(
            1,
            ProgressEventType::taskCompleted(),
            'task',
            42,
            'Finished review',
            CarbonImmutable::parse('2026-08-18 12:00:00'),
            'task:completed:42',
            ['completed' => true],
        )->withId(7);

        $array = $event->toArray();

        $this->assertSame(7, $array['id']);
        $this->assertSame('task_completed', $array['event_type']);
        $this->assertSame('task', $array['entity_type']);
        $this->assertSame(42, $array['entity_id']);
        $this->assertSame('task:completed:42', $array['operation_id']);
        $this->assertSame(['completed' => true], $array['payload']);
    }
}
