<?php

namespace Tests\Unit;

use App\Domain\Programs\Program;
use App\Domain\Programs\ValueObjects\ProgramStatus;
use App\Domain\Programs\ValueObjects\ProgramWorkloadType;
use App\Domain\Reconciliation\MorningRecoveryService;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\ValueObjects\TaskStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class MorningRecoveryServiceTest extends TestCase
{
    private MorningRecoveryService $service;

    protected function setUp(): void
    {
        $this->service = new MorningRecoveryService;
    }

    private function missedTask(int $id, ?CarbonImmutable $dueAt = null): Task
    {
        return Task::create(
            1,
            "Task {$id}",
            null,
            null,
            null,
            null,
            3,
            30,
            $dueAt,
        )
            ->withId($id)
            ->withStatus(TaskStatus::scheduled())
            ->withStatus(TaskStatus::missed());
    }

    private function program(string $status): Program
    {
        $program = Program::create(
            1,
            'Program',
            null,
            null,
            ProgramWorkloadType::flexible(),
        );

        if ($status === 'active') {
            return $program;
        }

        return $program->withStatus(new ProgramStatus($status));
    }

    public function test_sort_by_deadline_puts_nearest_deadline_first(): void
    {
        $later = $this->missedTask(1, CarbonImmutable::parse('2026-08-20'));
        $nearest = $this->missedTask(2, CarbonImmutable::parse('2026-08-19'));
        $none = $this->missedTask(3);

        $sorted = $this->service->sortByDeadline([$later, $none, $nearest]);

        $this->assertSame([2, 1, 3], array_column($sorted, 'id'));
    }

    public function test_sort_by_deadline_is_deterministic_for_equal_deadlines(): void
    {
        $a = $this->missedTask(7, CarbonImmutable::parse('2026-08-19'));
        $b = $this->missedTask(3, CarbonImmutable::parse('2026-08-19'));

        $sorted = $this->service->sortByDeadline([$a, $b]);

        $this->assertSame([3, 7], array_column($sorted, 'id'));
    }

    public function test_program_invalid_reason_is_null_when_active(): void
    {
        $this->assertNull($this->service->programInvalidReason($this->missedTask(1), $this->program('active')));
        $this->assertNull($this->service->programInvalidReason($this->missedTask(1), null));
    }

    public function test_program_invalid_reason_detects_terminal_programs(): void
    {
        $task = $this->missedTask(1);

        $this->assertSame('program_completed', $this->service->programInvalidReason($task, $this->program('completed')));
        $this->assertSame('program_dropped', $this->service->programInvalidReason($task, $this->program('dropped')));
    }

    public function test_allowed_actions_with_valid_program(): void
    {
        $task = $this->missedTask(1);

        $this->assertSame(
            ['reschedule', 'complete', 'backlog'],
            $this->service->allowedActions($task, $this->program('active')),
        );
        $this->assertSame(
            ['reschedule', 'complete', 'backlog'],
            $this->service->allowedActions($task, null),
        );
    }

    public function test_allowed_actions_withhold_reschedule_for_terminal_program(): void
    {
        $task = $this->missedTask(1);

        $this->assertSame(
            ['complete', 'backlog'],
            $this->service->allowedActions($task, $this->program('completed')),
        );
        $this->assertSame(
            ['complete', 'backlog'],
            $this->service->allowedActions($task, $this->program('dropped')),
        );
    }
}
