<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Note detail (P27-007): note read surface from /notes/{id}, its knowledge
 * links, and Task/Goal linking writes. Version is surfaced so the browser's
 * Tiptap editor stays the authoring surface; this screen never edits content.
 */
final class NoteDetailScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public string $notice = '';

    public int $noteId = 0;

    public array $note = [];

    public array $links = [];

    public array $tasks = [];

    public array $goals = [];

    public function mount(): void
    {
        $this->noteId = (int) $this->param('noteId', 0);
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

        $res = KinevoApi::get('/notes/'.$this->noteId);

        if ($res->status() === 404) {
            $this->state = 'error';
            $this->error = 'Note not found.';

            return;
        }
        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        if (! $res->successful()) {
            $this->state = 'error';
            $this->error = 'Could not load note.';

            return;
        }

        $this->note = $res->json('note') ?? [];
        $this->error = '';

        $linksRes = KinevoApi::get('/notes/'.$this->noteId.'/links');
        $this->links = $linksRes->successful() ? ($linksRes->json('links') ?? []) : [];

        $tasksRes = KinevoApi::get('/tasks', ['limit' => 10]);
        $this->tasks = $tasksRes->successful() ? ($tasksRes->json('tasks') ?? []) : [];

        $goalsRes = KinevoApi::get('/goals');
        $this->goals = $goalsRes->successful() ? ($goalsRes->json('goals') ?? []) : [];

        $this->state = 'ready';
    }

    public function addTaskLink(int $taskId): void
    {
        $this->createLink('task', $taskId);
    }

    public function addGoalLink(int $goalId): void
    {
        $this->createLink('goal', $goalId);
    }

    public function removeLink(int $linkId): void
    {
        $res = KinevoApi::delete('/notes/'.$this->noteId.'/links/'.$linkId);

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

    public function backToNotes(): void
    {
        $this->replace('/notes');
    }

    private function createLink(string $targetType, int $targetId): void
    {
        $res = KinevoApi::post('/notes/'.$this->noteId.'/links', [
            'target_type' => $targetType,
            'target_id' => $targetId,
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

    public function render(): View
    {
        return view('note-detail', ['screen' => $this]);
    }
}
