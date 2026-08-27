<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Review (P27-007 companion): a read-only progress surface — how much of
 * today is spoken for (capacity), and overall goal progress. The browser
 * remains authoritative for scheduling; this screen never mutates.
 */
final class ReviewScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public array $capacity = [];

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

        $today = KinevoApi::get('/today', ['date' => date('Y-m-d')]);
        $goals = KinevoApi::get('/goals');

        if ($today->successful() && $goals->successful()) {
            $this->capacity = $today->json('capacity') ?? [];
            $this->goals = $goals->json('goals') ?? [];
            $this->state = 'ready';
        } else {
            $this->state = 'error';
            $this->error = 'Review unavailable — retry when reachable.';
        }
    }

    public function render(): View
    {
        return view('review', ['screen' => $this]);
    }
}
