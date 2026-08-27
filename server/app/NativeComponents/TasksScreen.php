<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Tasks (P27-001 shell tab → P27-004 execution companion): lists the
 * shared /tasks surface for the active workspace.
 */
final class TasksScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public array $tasks = [];

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

        $res = KinevoApi::get('/tasks');

        if ($res->successful()) {
            $data = $res->json();
            $this->tasks = $data['tasks'] ?? $data['data'] ?? [];
            $this->state = 'ready';
        } elseif ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';
        } else {
            $this->state = 'error';
            $this->error = 'Could not load tasks.';
        }
    }

    /** P27-004 task execution — advance a task through the shared state machine. */
    public function execute(int $taskId, string $to): void
    {
        $res = KinevoApi::post('/tasks/'.$taskId.'/status', ['status' => $to]);
        if (! $res->successful()) {
            $this->error = 'Could not update task — retry.';
            $this->state = 'error';

            return;
        }
        $this->reload();
    }

    public function render(): View
    {
        return view('tasks', ['screen' => $this]);
    }
}
