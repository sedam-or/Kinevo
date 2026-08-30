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

    public string $notice = '';

    public function goCapture(): void
    {
        $this->navigate('/capture');
    }

    /** Start the primary task from the Today spine (write path, P27-002). */
    public function start(int $taskId): void
    {
        $this->transitionTo($taskId, 'in_progress');
    }

    /** Complete the task from the Today spine. */
    public function complete(int $taskId): void
    {
        $this->transitionTo($taskId, 'completed');
    }

    /** Reschedule where allowed — move the task to the next free slot (auto-swap). */
    public function reschedule(int $taskId): void
    {
        $res = KinevoApi::post('/tasks/'.$taskId.'/auto-swap', [
            'date' => date('Y-m-d'),
            'duration_minutes' => 30,
        ]);
        $this->handleMutation($res, $taskId, 'Moved to a free slot.');
    }

    private function transitionTo(int $taskId, string $status): void
    {
        $version = null;
        foreach ($this->events as $event) {
            if (($event['task']['id'] ?? null) === $taskId) {
                $version = $event['task']['version'] ?? null;
                break;
            }
        }
        $res = KinevoApi::post('/tasks/'.$taskId.'/status', [
            'status' => $status,
            'version' => $version,
        ]);
        $this->handleMutation($res, $taskId, $status === 'completed' ? 'Task completed ✓' : 'Task started.');
    }

    private function handleMutation($res, int $taskId, string $successMessage): void
    {
        if ($res->successful()) {
            $this->notice = $successMessage;
            $this->error = '';
            $this->reload();

            return;
        }
        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        if ($res->status() === 409) {
            $this->state = 'conflict';
            $this->error = 'Changed elsewhere — reload to continue.';

            return;
        }
        if ($res->status() === 403) {
            $this->state = 'entitlement';
            $this->error = 'Plan limit reached — upgrade on the web app.';

            return;
        }
        $server = $res->json('error');
        $this->notice = '';
        $this->error = $server !== null && $server !== ''
            ? 'Blocked: '.$server
            : 'Action failed — retry.';
        $this->state = 'ready';
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
