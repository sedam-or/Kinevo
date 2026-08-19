<?php

namespace Tests\Unit;

use App\Domain\Progress\ProgressEventService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProgressEventServiceTest extends TestCase
{
    private ProgressEventService $service;

    protected function setUp(): void
    {
        $this->service = new ProgressEventService;
    }

    #[Test]
    public function task_completed_event_references_the_domain_change(): void
    {
        $event = $this->service->taskCompleted(1, 42, 'Review PR');

        $this->assertSame('task_completed', $event->eventType->value);
        $this->assertSame('task', $event->entityType);
        $this->assertSame(42, $event->entityId);
        $this->assertSame('task:completed:42', $event->operationId);
        $this->assertSame('Review PR', $event->title);
    }

    #[Test]
    public function milestone_advanced_is_distinct_from_completed(): void
    {
        $advanced = $this->service->milestoneAdvanced(1, 5, 'Milestone A');
        $completed = $this->service->milestoneCompleted(1, 5, 'Milestone A');

        $this->assertSame('milestone_advanced', $advanced->eventType->value);
        $this->assertSame('milestone:advanced:5', $advanced->operationId);
        $this->assertSame('milestone_completed', $completed->eventType->value);
        $this->assertSame('milestone:completed:5', $completed->operationId);
    }
}
