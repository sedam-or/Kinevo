<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Goals (P27-006): goals list + AI breakdown trigger. Proposals are created
 * server-side via /goals/{id}/breakdown-proposals and surfaced here as
 * pending invites — the browser keeps final accept.
 */
final class GoalScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public array $goals = [];

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

        $res = KinevoApi::get('/goals');

        if ($res->successful()) {
            $this->goals = $res->json('goals') ?? [];
            $this->state = 'ready';
        } elseif ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';
        } else {
            $this->state = 'error';
            $this->error = 'Could not load goals.';
        }
    }

    public function proposeBreakdown(int $goalId): void
    {
        $res = KinevoApi::post('/goals/'.$goalId.'/breakdown-proposals');
        if ($res->successful()) {
            $this->reload();

            return;
        }
        $code = $res->json('code');
        if ($res->status() === 403 && $code === 'ENTITLEMENT_LIMIT') {
            $this->state = 'entitlement';
            $this->error = 'Plan limit reached — upgrade on the web app to keep using AI.';
        } elseif ($code === 'AI_PROVIDER_UNAVAILABLE' || $code === 'AI_CREDITS_EXHAUSTED' || $res->status() === 429) {
            $this->state = 'error';
            $this->error = 'AI is not ready — check provider on the web app.';
        } else {
            $this->state = 'error';
            $this->error = 'Breakdown request failed — retry on the web app.';
        }
    }

    public function render(): View
    {
        return view('goals', ['screen' => $this]);
    }
}
