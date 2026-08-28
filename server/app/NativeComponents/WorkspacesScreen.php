<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Workspace switching (P27-011): list shared workspaces, mark the active
 * one. Workspace context persists server-side via the default workspace.
 */
final class WorkspacesScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public array $workspaces = [];

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

        $res = KinevoApi::get('/workspaces');

        if ($res->successful()) {
            $data = $res->json();
            $this->workspaces = $data['workspaces'] ?? $data['data'] ?? [];
            $this->state = 'ready';
        } elseif ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';
        } else {
            $this->state = 'error';
            $this->error = 'Could not load workspaces.';
        }
    }

    /** P27-011 switch — make a workspace the default context (server-persisted). */
    public function setDefault(int $workspaceId): void
    {
        $res = KinevoApi::post('/workspaces/'.$workspaceId.'/default', []);

        if ($res->successful()) {
            $this->error = '';
            $this->reload();

            return;
        }
        if ($res->status() === 409) {
            $this->state = 'conflict';
            $this->error = 'Workspace changed elsewhere — reload to retry.';

            return;
        }
        if ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';

            return;
        }
        $this->error = 'Could not switch workspace — retry.';
    }

    public function render(): View
    {
        return view('workspaces', ['screen' => $this]);
    }
}
