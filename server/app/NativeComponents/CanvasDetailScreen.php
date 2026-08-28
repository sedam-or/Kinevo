<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Canvas companion detail (P27-008): read-only view of a shared canvas and
 * its knowledge links. Excalidraw owns drawing on the web; this surface is a
 * mirror that never exposes a false full-edit affordance.
 */
final class CanvasDetailScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public string $notice = '';

    public int $canvasId = 0;

    public array $canvas = [];

    public array $links = [];

    public array $tasks = [];

    public function mount(): void
    {
        $this->canvasId = (int) $this->param('canvasId', 0);
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

        $res = KinevoApi::get('/canvases/'.$this->canvasId);

        if ($res->status() === 404) {
            $this->state = 'error';
            $this->error = 'Canvas not found.';

            return;
        }
        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        if (! $res->successful()) {
            $this->state = 'error';
            $this->error = 'Could not load canvas.';

            return;
        }

        $this->canvas = $res->json('canvas') ?? [];
        $this->error = '';

        $linksRes = KinevoApi::get('/canvases/'.$this->canvasId.'/links');
        $this->links = $linksRes->successful() ? ($linksRes->json('links') ?? []) : [];

        $tasksRes = KinevoApi::get('/tasks');
        $this->tasks = $tasksRes->successful() ? ($tasksRes->json('tasks') ?? []) : [];

        $this->state = 'ready';
    }

    public function linkTask(int $taskId): void
    {
        $res = KinevoApi::post('/canvases/'.$this->canvasId.'/links', [
            'target_type' => 'task',
            'target_id' => $taskId,
            'link_type' => 'references',
        ]);

        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        if ($res->status() === 409) {
            $this->state = 'conflict';
            $this->error = 'That link already exists — reload to continue.';

            return;
        }
        if ($res->status() === 422) {
            $this->state = 'error';
            $this->error = 'Invalid link: '.implode(' ', collect($res->json('errors'))->flatten()->all());

            return;
        }
        if ($res->successful()) {
            $this->notice = 'Linked.';
            $this->error = '';
            $this->reload();

            return;
        }
        $this->error = 'Could not create link — retry.';
    }

    public function removeLink(int $linkId): void
    {
        $res = KinevoApi::delete('/canvases/'.$this->canvasId.'/links/'.$linkId);

        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        if ($res->successful() || $res->status() === 404) {
            $this->notice = 'Link removed.';
            $this->reload();

            return;
        }
        $this->error = 'Could not remove link — retry.';
    }

    public function openTask(int $taskId): void
    {
        $this->navigate('/tasks/'.$taskId);
    }

    public function backToCanvases(): void
    {
        $this->replace('/canvases');
    }

    public function render(): View
    {
        return view('canvas-detail', ['screen' => $this]);
    }
}
