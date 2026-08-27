<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Today (P27-002): the NOW/NEXT/LATER spine rendered from the shared
 * /today contract. States cover loading / unauthorized / offline /
 * conflict(409) / error / ready (P27-012 state UX).
 */
final class TodayScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public array $events = [];

    public array $emptySlots = [];

    public array $capacity = [];

    public string $userName = '';

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

        $res = KinevoApi::get('/today', ['date' => date('Y-m-d')]);

        switch ($res->status()) {
            case 200:
                $data = $res->json();
                $this->events = $data['events'] ?? [];
                $this->emptySlots = $data['empty_slots'] ?? [];
                $this->capacity = $data['capacity'] ?? [];
                $this->userName = $data['user']['name'] ?? '';
                $this->state = 'ready';
                break;
            case 401:
                KinevoApi::logout();
                $this->state = 'unauthorized';
                break;
            case 409:
                $this->state = 'conflict';
                $this->error = 'Stale schedule — pull to refresh.';
                break;
            default:
                $this->state = 'error';
                $this->error = 'Could not load Today.';
        }
    }

    public function goCapture(): void
    {
        $this->navigate('/capture');
    }

    public function signIn(): void
    {
        $ok = KinevoApi::login(
            (string) config('native.dev_email', 'test@example.com'),
            (string) config('native.dev_password', 'password')
        );

        if ($ok) {
            $this->reload();
        } else {
            $this->state = 'error';
            $this->error = 'Sign-in failed — check credentials.';
        }
    }

    public function render(): View
    {
        return view('today', ['screen' => $this]);
    }
}
