<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Mobile Notes companion (P27-007): read the shared /notes surface.
 */
final class NotesScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public array $notes = [];

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

        $res = KinevoApi::get('/notes');

        if ($res->successful()) {
            $this->notes = $res->json('notes') ?? $res->json('data') ?? [];
            $this->state = 'ready';
        } elseif ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';
        } else {
            $this->state = 'error';
            $this->error = 'Could not load notes.';
        }
    }

    public function render(): View
    {
        return view('notes', ['screen' => $this]);
    }
}
