<?php

namespace Tests\Feature\E2E;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-150 — Golden One-Week E2E.
 *
 * Walks the complete user journey in order:
 *
 *   Login → Goal → Milestone → Program → Task → Schedule → Today →
 *   Execute → Complete → Activity → Progress → Analytics → Capacity →
 *   Future Schedule
 *
 * Every assertion is made against user-visible API responses — the same
 * payloads the UI renders. Database-only assertions are deliberately not
 * used (TASK-150: "Database-only assertions are insufficient").
 *
 * The walked week is the CURRENT week (Monday-based), discovered at runtime:
 * analytics/activity events are recorded at "now", so a hardcoded window
 * silently broke the moment the calendar left it.
 */
final class GoldenWeekJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_golden_one_week_journey(): void
    {
        $weekStart = CarbonImmutable::now()->startOfWeek()->toDateString();
        $weekEnd = CarbonImmutable::now()->startOfWeek()->addDays(6)->toDateString();
        $nextWeekStart = CarbonImmutable::now()->startOfWeek()->addDays(7)->toDateString();
        $nextWeekEnd = CarbonImmutable::now()->startOfWeek()->addDays(13)->toDateString();
        // ── Login ────────────────────────────────────────────────────────
        // First setup registers the single owner account; login issues the
        // session token used for every subsequent step.
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
        ])->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token', 'profile']);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
        ])->assertStatus(200)
            ->assertJsonPath('user.email', 'owner@example.com');

        $token = $login->json('token');
        $auth = $this->withToken($token);

        $auth->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'owner@example.com');

        // ── Goal ─────────────────────────────────────────────────────────
        $goal = $auth->postJson('/api/v1/goals', [
            'title' => 'Finish the thesis',
            'horizon' => 'quarterly',
            'target_date' => CarbonImmutable::now()->addMonths(2)->toDateString(),
        ])->assertStatus(201)
            ->assertJsonPath('goal.title', 'Finish the thesis')
            ->assertJsonPath('goal.status', 'draft')
            ->assertJsonPath('goal.progress', 0)
            ->json('goal');

        // ── Milestone ────────────────────────────────────────────────────
        $milestone = $auth->postJson("/api/v1/goals/{$goal['id']}/milestones", [
            'title' => 'Complete literature review',
            'target_date' => CarbonImmutable::now()->addMonths(1)->toDateString(),
            'estimated_minutes' => 600,
        ])->assertStatus(201)
            ->assertJsonPath('milestone.goal_id', $goal['id'])
            ->assertJsonPath('milestone.sequence', 1)
            ->assertJsonPath('milestone.status', 'planned')
            ->json('milestone');

        // ── Program ──────────────────────────────────────────────────────
        $program = $auth->postJson('/api/v1/programs', [
            'name' => 'Deep writing habit',
            'category' => 'Growth',
            'workload_type' => 'structured',
            'weekly_target_minutes' => 300,
        ])->assertStatus(201)
            ->assertJsonPath('program.status', 'active')
            ->json('program');

        // ── Task ─────────────────────────────────────────────────────────
        $task = $auth->postJson('/api/v1/tasks', [
            'title' => 'Draft chapter 3',
            'priority_tier' => 1,
            'estimated_minutes' => 60,
            'due_at' => $weekEnd.'T17:00:00',
            'goal_id' => $goal['id'],
            'milestone_id' => $milestone['id'],
            'program_id' => $program['id'],
        ])->assertStatus(201)
            ->assertJsonPath('task.title', 'Draft chapter 3')
            ->assertJsonPath('task.status', 'backlog')
            ->assertJsonPath('task.priority_tier', 1)
            ->json('task');

        // ── Schedule (auto-schedule draft over the golden week) ──────────
        $draftResponse = $auth->postJson('/api/v1/schedule/draft', [
            'from' => $weekStart,
            'to' => $weekEnd,
        ])->assertStatus(200)
            ->assertJsonPath('base_version', 1);

        $draft = $draftResponse->json('draft');
        $this->assertNotEmpty($draft['assignments']);
        $this->assertSame((string) $task['id'], $draft['assignments'][0]['task_id']);
        $this->assertSame('Draft chapter 3', $draft['assignments'][0]['title']);
        $this->assertTrue($draftResponse->json('draft.unassigned') === [] || is_array($draftResponse->json('draft.unassigned')));

        $applied = $auth->postJson('/api/v1/schedule/draft/apply', [
            'draft' => $draft,
            'base_version' => $draftResponse->json('base_version'),
        ])->assertStatus(200)
            ->assertJsonPath('applied', true)
            ->assertJsonPath('version', 2);

        $this->assertGreaterThanOrEqual(1, count($applied->json('assignments')));

        // The scheduled day is discovered from the user-visible schedule view.
        $range = $auth->getJson('/api/v1/schedule?from='.$weekStart.'&to='.$weekEnd)
            ->assertStatus(200)
            ->assertJsonPath('from', $weekStart)
            ->assertJsonPath('to', $weekEnd)
            ->json();

        $this->assertNotEmpty($range['events']);
        $goldenEvent = null;
        foreach ($range['events'] as $event) {
            if (($event['task']['title'] ?? null) === 'Draft chapter 3') {
                $goldenEvent = $event;
                break;
            }
        }
        $this->assertNotNull($goldenEvent, 'Scheduled task must appear in the week schedule view.');
        $scheduledDay = $goldenEvent['assignment']['date']
            ?? substr((string) $goldenEvent['assignment']['start_at'], 0, 10);

        // ── Today ────────────────────────────────────────────────────────
        $auth->getJson('/api/v1/today?date='.$scheduledDay)
            ->assertStatus(200)
            ->assertJsonPath('date', $scheduledDay)
            ->assertJsonPath('schedule_version', 2)
            ->assertJsonPath('events.0.task.title', 'Draft chapter 3')
            ->assertJsonPath('events.0.locked', false)
            ->assertJsonPath('events.0.conflict', false);
        $todayCapacity = $auth->getJson('/api/v1/today?date='.$scheduledDay)->json('capacity');
        $this->assertGreaterThanOrEqual(60, $todayCapacity['scheduled_minutes']);

        // ── Execute ──────────────────────────────────────────────────────
        $session = $auth->postJson('/api/v1/execution/start', ['task_id' => $task['id']])
            ->assertStatus(201)
            ->assertJsonPath('execution.status', 'running')
            ->json('execution');

        $auth->postJson("/api/v1/execution/{$session['id']}/complete")
            ->assertStatus(200)
            ->assertJsonPath('execution.status', 'completed')
            ->assertJsonPath('focus_session.task_id', $task['id']);

        // ── Complete ─────────────────────────────────────────────────────
        // Starting execution moves the task in_progress and completing the
        // session completes it (no subtasks remain); verified through the
        // user-visible task view.
        $auth->getJson("/api/v1/tasks/{$task['id']}")
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'completed');

        // ── Activity ─────────────────────────────────────────────────────
        $logs = $auth->getJson('/api/v1/logs?event_type=task_completed')
            ->assertStatus(200)
            ->json('logs');
        $this->assertNotEmpty($logs);
        $this->assertSame('task_completed', $logs[0]['event_type']);

        // ── Progress ─────────────────────────────────────────────────────
        $auth->postJson('/api/v1/progress', [
            'event_type' => 'experiment_recorded',
            'title' => 'Reviewed chapter feedback',
            'entity_type' => 'task',
            'entity_id' => $task['id'],
        ])->assertStatus(201)
            ->assertJsonPath('event.event_type', 'experiment_recorded');

        $progressEvents = $auth->getJson('/api/v1/progress')
            ->assertStatus(200)
            ->json('events');
        $this->assertNotEmpty($progressEvents);
        $this->assertTrue(collect($progressEvents)->contains('event_type', 'experiment_recorded'));

        // ── Analytics + Capacity ─────────────────────────────────────────
        $overview = $auth->getJson('/api/v1/analytics/overview?from='.$weekStart.'&to='.$weekEnd)
            ->assertStatus(200)
            ->json();

        $this->assertSame(1, $overview['task_completion']['total_tasks']);
        $this->assertSame(1, $overview['task_completion']['completed_tasks']);
        $this->assertSame(1, $overview['activity']['by_type']['task_completed']);
        $this->assertSame(1, $overview['goal_progress']['total_goals']);
        $this->assertSame(1, $overview['goal_progress']['total_milestones']);
        $this->assertArrayHasKey('work_life', $overview);
        $this->assertGreaterThanOrEqual(1, $overview['focus']['total_sessions']);

        // Capacity view: one entry per day of the selected range.
        $this->assertCount(7, $overview['capacity']['days']);
        $capacityDays = collect($overview['capacity']['days']);
        $this->assertTrue($capacityDays->contains('date', $scheduledDay));
        $this->assertSame(
            60,
            $capacityDays->firstWhere('date', $scheduledDay)['scheduled_minutes'],
        );

        // ── Future Schedule ──────────────────────────────────────────────
        $futureTask = $auth->postJson('/api/v1/tasks', [
            'title' => 'Plan next week',
            'priority_tier' => 2,
            'estimated_minutes' => 30,
            'goal_id' => $goal['id'],
        ])->assertStatus(201)
            ->assertJsonPath('task.status', 'backlog')
            ->json('task');

        $futureDraft = $auth->postJson('/api/v1/schedule/draft', [
            'from' => $nextWeekStart,
            'to' => $nextWeekEnd,
        ])->assertStatus(200)->json();

        $auth->postJson('/api/v1/schedule/draft/apply', [
            'draft' => $futureDraft['draft'],
            'base_version' => $futureDraft['base_version'],
        ])->assertStatus(200)
            ->assertJsonPath('applied', true);

        $future = $auth->getJson('/api/v1/schedule?from='.$nextWeekStart.'&to='.$nextWeekEnd)
            ->assertStatus(200)
            ->json();

        $this->assertNotEmpty($future['events']);
        $this->assertSame(
            'Plan next week',
            $future['events'][0]['task']['title'],
        );
        $this->assertGreaterThanOrEqual(
            strtotime($nextWeekStart),
            strtotime($future['events'][0]['assignment']['date']),
        );
    }
}
