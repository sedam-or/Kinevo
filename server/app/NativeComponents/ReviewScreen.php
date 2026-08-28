<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Review (P27-009 companion): a read-only progress surface — how much of today
 * is spoken for (capacity), recent task completion + focus from the analytics
 * overview, and overall goal progress. Metrics are server-authored only; this
 * screen never derives values.
 */
final class ReviewScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public array $capacity = [];

    public array $goals = [];

    public array $completion = [];

    public array $focus = [];

    public array $goalProgress = [];

    public function mount(): void
    {
        $this->reload();
    }

    public function reload(): void
    {
        $this->state = 'loading';
        $this->refreshOffline();

        if (! KinevoApi::authed()) {
            $this->state = 'unauthorized';

            return;
        }
        if ($this->offline) {
            $this->state = 'offline';

            return;
        }

        $today = KinevoApi::get('/today', ['date' => date('Y-m-d')]);
        $goals = KinevoApi::get('/goals');
        $overview = KinevoApi::get('/analytics/overview', [
            'from' => date('Y-m-d', strtotime('-7 days')),
            'to' => date('Y-m-d'),
        ]);

        $hasCore = $today->successful() && $goals->successful();

        if ($hasCore) {
            $this->capacity = $today->json('capacity') ?? [];
            $this->goals = $goals->json('goals') ?? [];
        }
        if ($overview->successful()) {
            $this->completion = $overview->json('task_completion') ?? [];
            $this->focus = $overview->json('focus') ?? [];
            $this->goalProgress = $overview->json('goal_progress') ?? [];
        }

        if (! $hasCore) {
            $this->state = 'error';
            $this->error = 'Review unavailable — retry when reachable.';

            return;
        }

        $this->state = 'ready';
    }

    public function openGoal(int $goalId): void
    {
        $this->navigate('/goals/'.$goalId);
    }

    public function openToday(): void
    {
        $this->replace('/');
    }

    public function render(): View
    {
        return view('review', ['screen' => $this]);
    }
}
