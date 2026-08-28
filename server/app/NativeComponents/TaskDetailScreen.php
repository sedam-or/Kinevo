<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Mobile Task Execution (P27-004): the task detail companion. Lifecycle
 * (view/start/complete/partial) rides the shared /tasks contracts, timer
 * states ride the ExecutionTimer contract (/execution). Every mutation is
 * server-validated; 422 invalid transitions surface the server's reason,
 * 403 maps to the entitlement state, 409 to conflict (P27-012).
 */
final class TaskDetailScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public string $notice = '';

    public array $task = [];

    public array $subtasks = [];

    public array $execution = [];

    /** Knowledge links pointing AT this task (P27-008 reachability). */
    public array $linkingCanvases = [];

    public int $taskId = 0;

    public function mount(): void
    {
        $this->taskId = (int) $this->param('taskId', 0);
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
        if ($this->taskId <= 0) {
            $this->state = 'error';
            $this->error = 'No task selected.';

            return;
        }

        $res = KinevoApi::get('/tasks/'.$this->taskId);

        if ($res->status() === 404) {
            $this->state = 'error';
            $this->error = 'Task not found.';

            return;
        }
        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        if ($res->status() === 403) {
            $this->state = 'entitlement';

            return;
        }
        if (! $res->successful()) {
            $this->state = 'error';
            $this->error = 'Could not load task.';

            return;
        }

        $this->task = $res->json('task') ?? [];
        $this->error = '';
        $subRes = KinevoApi::get('/tasks/'.$this->taskId.'/subtasks');
        $this->subtasks = $subRes->successful()
            ? ($subRes->json('subtasks') ?? $subRes->json('data') ?? [])
            : [];
        $active = KinevoApi::get('/execution/active');
        $this->execution = $active->successful() ? ($active->json('execution') ?? $active->json('session') ?? []) : [];
        $this->linkingCanvases = $this->loadLinkingCanvases();
        $this->state = 'ready';
    }

    /** Canvases that reference this task, surfaced so the companion is reachable. */
    private function loadLinkingCanvases(): array
    {
        $links = KinevoApi::get('/knowledge/links', ['target_type' => 'task', 'target_id' => $this->taskId]);
        if (! $links->successful()) {
            return [];
        }

        return array_values(array_filter(
            $links->json('links') ?? [],
            static fn ($l) => ($l['source_type'] ?? null) === 'canvas',
        ));
    }

    public function openCanvas(int $canvasId): void
    {
        $this->navigate('/canvases/'.$canvasId);
    }

    /** Lifecycle: start working (in_progress). */
    public function start(): void
    {
        $this->transitionTo('in_progress');
    }

    /** Lifecycle: complete (state machine may reject → server reason shown). */
    public function complete(): void
    {
        $this->transitionTo('completed');
    }

    /** Lifecycle: partial completion (persists progress + continuation). */
    public function partialComplete(): void
    {
        $res = KinevoApi::post('/tasks/'.$this->taskId.'/partial-complete', []);
        $this->handleMutation($res, 'Partial completion saved.');
    }

    /** Subtask toggle (persisted server-side). */
    public function toggleSubtask(int $subtaskId): void
    {
        $res = KinevoApi::post('/tasks/'.$this->taskId.'/subtasks/'.$subtaskId.'/toggle', []);
        $this->handleMutation($res, 'Subtask updated.');
    }

    /** Timer wiring — ExecutionTimer contract, all states server-derived. */
    public function timerStart(): void
    {
        $res = KinevoApi::post('/execution/start', ['task_id' => $this->taskId]);
        $this->handleMutation($res, 'Timer running.');
    }

    public function timerPause(): void
    {
        $res = KinevoApi::post('/execution/'.($this->execution['id'] ?? 0).'/pause', []);
        $this->handleMutation($res, 'Timer paused.');
    }

    public function timerResume(): void
    {
        $res = KinevoApi::post('/execution/'.($this->execution['id'] ?? 0).'/resume', []);
        $this->handleMutation($res, 'Timer resumed.');
    }

    public function timerComplete(): void
    {
        $res = KinevoApi::post('/execution/'.($this->execution['id'] ?? 0).'/complete', []);
        $this->handleMutation($res, 'Session completed.');
    }

    public function backToTasks(): void
    {
        $this->replace('/tasks');
    }

    private function transitionTo(string $to): void
    {
        $res = KinevoApi::post('/tasks/'.$this->taskId.'/status', [
            'status' => $to,
            'version' => $this->task['version'] ?? null,
        ]);
        $this->handleMutation($res, $to === 'completed' ? 'Task completed ✓' : 'Task updated.');
    }

    private function handleMutation($res, string $successMessage): void
    {
        if ($res->successful()) {
            $this->notice = $successMessage;
            $this->error = '';
            $this->reload();

            return;
        }

        $server = $res->json('error');
        $errors = $res->json('errors');

        if ($res->status() === 422) {
            $this->notice = '';
            $this->error = $server !== null && $server !== ''
                ? 'Blocked: '.$server
                : 'Blocked: '.implode(' ', collect($errors ?? [])->flatten()->all());
            $this->state = 'ready';

            return;
        }
        if ($res->status() === 409) {
            $this->state = 'conflict';
            $this->error = 'Changed elsewhere — reload to continue.';

            return;
        }
        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        if ($res->status() === 403) {
            $this->state = 'entitlement';

            return;
        }
        $this->notice = '';
        $this->error = 'Action failed — retry.';
        $this->state = 'ready';
    }

    public function render(): View
    {
        return view('task-detail', ['screen' => $this]);
    }
}
