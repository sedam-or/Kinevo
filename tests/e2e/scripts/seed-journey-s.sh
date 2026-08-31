#!/usr/bin/env sh
# ADR-016 — seeds the weekly-draft state required by browser-e2e.md journeys
# S1–S4. Run BEFORE `npx playwright test scheduler-trigger.spec.ts`:
#
#   docker compose -f infrastructure/docker-compose.yml exec -T app sh < tests/e2e/scripts/seed-journey-s.sh
#
# Idempotent: removes previous Journey S rows first, then drives the REAL
# weekly trigger (`schedule:prepare-weekly`). If the trigger does not produce
# a pending draft for the sandbox owner (e.g. dedup from a previous applied
# week), the script falls back to writing the SAME real-shape draft through
# the domain generator so the browser journey is deterministic. The trigger
# itself is covered by WeeklyPrepareCommandTest (feature evidence).

php artisan tinker <<'PHP'
$user = \App\Models\User::where('email', 'sampalan@gmail.com')->first();
if (! $user) {
    echo "SEED_S_FAIL owner user missing — register first\n";
    exit(1);
}

// Clean previous Journey S fixtures.
$old = \App\Models\Task::where('user_id', $user->id)->where('title', 'like', 'JourneyS%')->pluck('id');
\DB::table('schedule_drafts')->where('user_id', $user->id)->delete();
\DB::table('schedule_assignment_history')->whereIn('task_id', $old)->delete();
\App\Models\TaskAssignment::whereIn('task_id', $old)->delete();
\App\Models\Task::whereIn('id', $old)->delete();
\DB::table('hard_landscape_events')->where('user_id', $user->id)->where('title', 'like', 'JourneyS%')->delete();
\DB::table('schedule_overrides')->where('user_id', $user->id)->delete();
\DB::table('schedule_states')->where('user_id', $user->id)->delete();
\DB::table('notifications')->where('user_id', $user->id)->whereIn('type', ['weekly_draft_ready', 'schedule_needs_review'])->delete();

// Backlog work the weekly draft can place (no assignments of its own).
\App\Models\Task::create([
    'user_id' => $user->id,
    'title' => 'JourneyS weekly report',
    'status' => 'backlog',
    'priority_tier' => 2,
    'estimated_minutes' => 60,
    'progress_mode' => 'derived',
    'progress' => 0,
    'is_sacred_anchor' => false,
]);

// Drive the REAL weekly trigger for the owner.
\Artisan::call('schedule:prepare-weekly', ['--email' => 'sampalan@gmail.com']);
$output = \Artisan::output();

$pending = \App\Domain\Scheduling\Contracts\ScheduleDraftRepository::class;
$repo = app($pending);
$drafts = $repo->listPendingForUser($user->id);

if (count($drafts) < 1) {
    // Deterministic fallback: build the draft through the SAME domain paths
    // the trigger uses (assemble + generate) and persist it as pending.
    $assemble = app(\App\Application\Scheduling\AssembleScheduleInput::class);
    $generator = app(\App\Domain\Scheduling\ScheduleDraftGenerator::class);
    $from = now()->startOfWeek();
    $to = $from->copy()->addDays(6);
    $assembled = $assemble($user->id, $from, $to);
    $draft = $generator->generate($assembled['input']);
    $payload = [
        'draft' => [
            'assignments' => array_map(static fn ($a) => [
                'task_id' => $a->taskId,
                'title' => $a->title,
                'start' => $a->slot->start->toISOString(),
                'end' => $a->slot->end->toISOString(),
            ], $draft->assignments),
            'unassigned' => array_map(static fn ($t) => [
                'task_id' => $t->taskId,
                'title' => $t->title,
                'reason' => $t->reason,
            ], $draft->unassigned),
        ],
        'base_version' => $assembled['base_version']->value,
    ];
    $repo->create(\App\Domain\Scheduling\ScheduleDraftRecord::weekly(
        $user->id,
        $payload,
        $assembled['base_version']->value,
        $from,
        $to,
        $from,
    ));
    $drafts = $repo->listPendingForUser($user->id);
}

if (count($drafts) < 1) {
    echo "SEED_S_FAIL: no pending weekly draft (trigger output: {$output})\n";
    exit(1);
}

echo "SEED_S_OK pending=".count($drafts)." (trigger: ".trim(str_replace("\n", ' ', $output)).")\n";
PHP
