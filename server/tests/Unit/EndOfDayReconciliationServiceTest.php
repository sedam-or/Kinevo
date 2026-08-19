<?php

namespace Tests\Unit;

use App\Domain\Reconciliation\EndOfDayReconciliationService;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\ValueObjects\TaskStatus;
use PHPUnit\Framework\TestCase;

class EndOfDayReconciliationServiceTest extends TestCase
{
    private EndOfDayReconciliationService $service;

    protected function setUp(): void
    {
        $this->service = new EndOfDayReconciliationService;
    }

    private function task(TaskStatus $status): Task
    {
        $task = Task::create(1, 'Task')->withId(1);

        return match ($status->value) {
            'backlog' => $task,
            'scheduled' => $task->withStatus(TaskStatus::scheduled()),
            'completed' => $task->withStatus(TaskStatus::completed()),
            'in_progress' => $task->withStatus(TaskStatus::inProgress()),
            'partial' => $task->withStatus(TaskStatus::inProgress())->withStatus(TaskStatus::partial()),
            'missed' => $task->withStatus(TaskStatus::scheduled())->withStatus(TaskStatus::missed()),
            default => throw new \InvalidArgumentException("No builder for {$status->value}"),
        };
    }

    public function test_scheduled_task_is_eligible_for_deadline(): void
    {
        $task = $this->task(TaskStatus::scheduled());
        $this->assertTrue($this->service->isEligibleForDeadline($task));
    }

    public function test_completed_task_is_not_eligible(): void
    {
        $task = $this->task(TaskStatus::completed());
        $this->assertFalse($this->service->isEligibleForDeadline($task));
    }

    public function test_backlog_task_is_not_eligible(): void
    {
        // Per the state machine, backlog cannot transition to missed.
        $task = $this->task(TaskStatus::backlog());
        $this->assertFalse($this->service->isEligibleForDeadline($task));
    }

    public function test_in_progress_task_is_not_eligible(): void
    {
        // in_progress cannot transition to missed (user actively working).
        $task = $this->task(TaskStatus::inProgress());
        $this->assertFalse($this->service->isEligibleForDeadline($task));
    }

    public function test_mark_missed_transitions_scheduled_task(): void
    {
        $task = $this->task(TaskStatus::scheduled());
        $missed = $this->service->markMissed($task);

        $this->assertTrue($missed->status->equals(TaskStatus::missed()));
        $this->assertNotSame($task->version, $missed->version);
    }

    public function test_mark_missed_is_idempotent_for_ineligible_task(): void
    {
        $task = $this->task(TaskStatus::completed());
        $result = $this->service->markMissed($task);

        $this->assertTrue($result->status->equals(TaskStatus::completed()));
        $this->assertSame($task->version, $result->version);
    }

    public function test_mark_missed_does_not_reprocess_already_missed(): void
    {
        // missed -> missed is not a valid transition, so it is not re-processed.
        $task = $this->task(TaskStatus::missed());
        $result = $this->service->markMissed($task);

        $this->assertTrue($result->status->equals(TaskStatus::missed()));
        $this->assertSame($task->version, $result->version);
    }

    public function test_reconcile_at_deadline_marks_eligible_and_skips_others(): void
    {
        $scheduled = $this->task(TaskStatus::scheduled());
        $completed = $this->task(TaskStatus::completed());
        $inProgress = $this->task(TaskStatus::inProgress());

        $reconciled = $this->service->reconcileAtDeadline([$scheduled, $completed, $inProgress]);

        $this->assertCount(1, $reconciled);
        $this->assertTrue($reconciled[0]->status->equals(TaskStatus::missed()));
    }

    public function test_reconcile_at_deadline_is_idempotent_on_retry(): void
    {
        // After reconciliation, tasks are missed; a retry yields no changes.
        $scheduled = $this->task(TaskStatus::scheduled());
        $firstPass = $this->service->reconcileAtDeadline([$scheduled]);
        $this->assertCount(1, $firstPass);

        $secondPass = $this->service->reconcileAtDeadline($firstPass);
        $this->assertCount(0, $secondPass);
    }

    public function test_scheduled_task_is_eligible_for_prompt(): void
    {
        $task = $this->task(TaskStatus::scheduled());
        $this->assertTrue($this->service->isEligibleForPrompt($task));
    }

    public function test_in_progress_task_is_eligible_for_prompt(): void
    {
        $task = $this->task(TaskStatus::inProgress());
        $this->assertTrue($this->service->isEligibleForPrompt($task));
    }

    public function test_completed_and_partial_tasks_are_not_eligible_for_prompt(): void
    {
        $this->assertFalse($this->service->isEligibleForPrompt($this->task(TaskStatus::completed())));
        // partial -> continued is the only onward path; a partial task is not
        // an "untouched" task, so it never prompts.
        $this->assertFalse($this->service->isEligibleForPrompt(
            $this->task(TaskStatus::partial()),
        ));
    }

    public function test_backlog_and_resolved_tasks_are_not_eligible_for_prompt(): void
    {
        $this->assertFalse($this->service->isEligibleForPrompt($this->task(TaskStatus::backlog())));
        $this->assertFalse($this->service->isEligibleForPrompt($this->task(TaskStatus::missed())));
    }

    public function test_prompt_tasks_filters_and_preserves_order(): void
    {
        $scheduled = $this->task(TaskStatus::scheduled());
        $completed = $this->task(TaskStatus::completed());
        $inProgress = $this->task(TaskStatus::inProgress());

        $prompted = $this->service->promptTasks([$scheduled, $completed, $inProgress]);

        $this->assertCount(2, $prompted);
        $this->assertTrue($prompted[0]->status->equals(TaskStatus::scheduled()));
        $this->assertTrue($prompted[1]->status->equals(TaskStatus::inProgress()));
    }
}
