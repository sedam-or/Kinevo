#!/usr/bin/env sh
# TASK-R7 — seeds the missed-task state required by browser-e2e.md Journey C
# (Recover). Run BEFORE `npx playwright test journey-c-e.spec.ts` — `make e2e`
# does this automatically right after its sandbox reset.
#
#   docker compose -f infrastructure/docker-compose.yml exec -T app sh < tests/e2e/scripts/seed-journey-c.sh
#
# Idempotent: removes previous Journey C rows first, then drives the real
# 23:59 deadline reconciliation (FR-47) instead of waiting for a cron hit —
# the seeded task reaches `missed` through the actual domain path, so the
# journey does not depend on scheduler timing.
php artisan tinker <<'PHP'
// Tinker resolves App\* models from the root namespace already.

$user = \App\Models\User::where('email', 'sampalan@gmail.com')->first();
if (! $user) {
    echo "owner user missing — register first\n";
    exit(1);
}

App\Models\Task::where(['user_id' => $user->id, 'title' => 'JourneyC missed report'])->delete();

$task = \App\Models\Task::create([
    'user_id' => $user->id,
    'title' => 'JourneyC missed report',
    'status' => 'scheduled',
    'priority_tier' => 2,
    'progress_mode' => 'derived',
    'progress' => 0,
]);

$date = now()->subDay()->toDateString();
App\Models\TaskAssignment::create([
    'user_id' => $user->id,
    'task_id' => $task->id,
    'date' => $date,
    'start_at' => $date.' 09:00:00',
    'end_at' => $date.' 10:00:00',
    'duration_minutes' => 60,
    'status' => 'scheduled',
    'source' => 'draft',
    'schedule_version' => 1,
    'locked' => false,
    'version' => 1,
]);

\Artisan::call('eod:reconcile', ['--phase' => 'deadline']);

$task->refresh();
if ((string) $task->status !== 'missed') {
    echo "JourneyC seed FAILED: task #{$task->id} status={$task->status} (deadline reconciliation did not flip it)\n";
    exit(1);
}

echo "JourneyC seeded: task #{$task->id} missed with yesterday assignment\n";
PHP
