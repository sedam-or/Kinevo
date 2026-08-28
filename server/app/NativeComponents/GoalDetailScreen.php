<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Goal detail (P27-005): goal read surface from the /goals/{id} contract,
 * plus its milestones from /goals/{id}/milestones. Metrics render exactly as
 * the backend reports them — no client-derived values.
 */
final class GoalDetailScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public int $goalId = 0;

    public array $goal = [];

    public array $milestones = [];

    public function mount(): void
    {
        $this->goalId = (int) $this->param('goalId', 0);
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
        if ($this->goalId <= 0) {
            $this->state = 'error';
            $this->error = 'No goal selected.';

            return;
        }

        $res = KinevoApi::get('/goals/'.$this->goalId);

        if ($res->status() === 404) {
            $this->state = 'error';
            $this->error = 'Goal not found.';

            return;
        }
        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        if (! $res->successful()) {
            $this->state = 'error';
            $this->error = 'Could not load goal.';

            return;
        }

        $this->goal = $res->json('goal') ?? [];
        $this->error = '';

        $msRes = KinevoApi::get('/goals/'.$this->goalId.'/milestones');
        $this->milestones = $msRes->successful() ? ($msRes->json('milestones') ?? []) : [];

        $this->state = 'ready';
    }

    public function backToGoals(): void
    {
        $this->replace('/goals');
    }

    public function openBreakdown(): void
    {
        $this->navigate('/goals/'.$this->goalId.'/breakdown');
    }

    public function render(): View
    {
        return view('goal-detail', ['screen' => $this]);
    }
}
