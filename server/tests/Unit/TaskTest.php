<?php

namespace Tests\Unit;

use App\Domain\Tasks\Subtask;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskProgressCalculator;
use App\Domain\Tasks\ValueObjects\TaskStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    #[Test]
    public function status_validates_allowed_values_and_terminals(): void
    {
        $this->assertTrue(TaskStatus::completed()->isTerminal());
        $this->assertTrue(TaskStatus::skipped()->isTerminal());
        $this->assertFalse(TaskStatus::backlog()->isTerminal());
        $this->assertFalse(TaskStatus::inProgress()->isTerminal());

        $this->expectException(InvalidArgumentException::class);
        new TaskStatus('on_hold');
    }

    #[Test]
    public function status_transitions_are_explicit(): void
    {
        $this->assertTrue(TaskStatus::backlog()->canTransitionTo(TaskStatus::scheduled()));
        $this->assertTrue(TaskStatus::scheduled()->canTransitionTo(TaskStatus::inProgress()));
        $this->assertTrue(TaskStatus::inProgress()->canTransitionTo(TaskStatus::completed()));
        $this->assertTrue(TaskStatus::inProgress()->canTransitionTo(TaskStatus::partial()));
        $this->assertTrue(TaskStatus::partial()->canTransitionTo(TaskStatus::continued()));
        $this->assertTrue(TaskStatus::continued()->canTransitionTo(TaskStatus::scheduled()));
        $this->assertTrue(TaskStatus::conflict()->canTransitionTo(TaskStatus::scheduled()));
        $this->assertTrue(TaskStatus::missed()->canTransitionTo(TaskStatus::backlog()));
        $this->assertTrue(TaskStatus::missed()->canTransitionTo(TaskStatus::scheduled()));
        // FR-48 Morning Recovery: a recovered task may be marked complete.
        $this->assertTrue(TaskStatus::missed()->canTransitionTo(TaskStatus::completed()));
        $this->assertFalse(TaskStatus::completed()->canTransitionTo(TaskStatus::conflict()));
        $this->assertFalse(TaskStatus::skipped()->canTransitionTo(TaskStatus::backlog()));
    }

    #[Test]
    public function task_can_be_created_with_context(): void
    {
        $task = Task::create(
            1,
            'Ship feature',
            'Polish and ship',
            10,
            20,
            30,
            2,
            60,
            CarbonImmutable::parse('2026-08-20'),
        );

        $this->assertSame('backlog', $task->status->value);
        $this->assertSame(10, $task->programId);
        $this->assertSame(20, $task->goalId);
        $this->assertSame(30, $task->milestoneId);
        $this->assertSame(2, $task->priorityTier);
        $this->assertSame(60, $task->estimatedMinutes);
        $this->assertSame(1, $task->version);
    }

    #[Test]
    public function invalid_status_transition_throws(): void
    {
        $task = Task::create(1, 'Task');
        $completed = $task->withStatus(TaskStatus::completed());

        $this->expectException(InvalidArgumentException::class);
        $completed->withStatus(TaskStatus::backlog());
    }

    #[Test]
    public function valid_status_transition_returns_new_instance(): void
    {
        $task = Task::create(1, 'Task');
        $scheduled = $task->withStatus(TaskStatus::scheduled());

        $this->assertSame('backlog', $task->status->value);
        $this->assertSame('scheduled', $scheduled->status->value);
        $this->assertSame(2, $scheduled->version);
    }

    #[Test]
    public function progress_is_bounded_to_0_100(): void
    {
        $task = Task::create(1, 'Task');
        $this->assertSame(50, $task->withProgress(50)->progress);

        $this->expectException(InvalidArgumentException::class);
        $task->withProgress(101);
    }

    #[Test]
    public function subtask_belongs_to_exactly_one_task(): void
    {
        $subtask = Subtask::create(1, 42, 'First step', 'Some notes', 3);

        $this->assertSame(42, $subtask->taskId);
        $this->assertSame('First step', $subtask->title);
        $this->assertSame('Some notes', $subtask->notes);
        $this->assertSame(3, $subtask->sequence);
        $this->assertFalse($subtask->completed);
        $this->assertSame(1, $subtask->version);
    }

    #[Test]
    public function toggling_subtask_bumps_version_only_on_change(): void
    {
        $subtask = Subtask::create(1, 1, 'Step');
        $checked = $subtask->withCompleted(true);

        $this->assertTrue($checked->completed);
        $this->assertSame(2, $checked->version);
        $this->assertSame(1, $subtask->version);
        $this->assertSame($checked, $checked->withCompleted(true));
    }

    #[Test]
    public function progress_calculator_uses_completed_over_total(): void
    {
        $calculator = new TaskProgressCalculator;
        $completed = Subtask::create(1, 1, 'Done')->withCompleted(true);

        $this->assertSame(0, $calculator->calculate([]));
        $this->assertSame(50, $calculator->calculate([
            $completed,
            Subtask::create(1, 1, 'Todo'),
        ]));
        $this->assertSame(100, $calculator->calculate([
            $completed,
            Subtask::create(1, 1, 'Done 2')->withCompleted(true),
        ]));
    }

    #[Test]
    public function to_array_exposes_contract_fields(): void
    {
        $task = Task::create(1, 'Launch', 'go', 5, 6, 7, 1, 30, null);

        $this->assertSame([
            'id' => 0,
            'user_id' => 1,
            'program_id' => 5,
            'goal_id' => 6,
            'milestone_id' => 7,
            'title' => 'Launch',
            'description' => 'go',
            'status' => 'backlog',
            'priority_tier' => 1,
            'estimated_minutes' => 30,
            'due_at' => null,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
            'workspace_id' => null,
            'is_sacred_anchor' => false,
        ], $task->toArray());
    }
}
