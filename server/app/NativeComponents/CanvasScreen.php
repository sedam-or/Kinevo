<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Canvas companion (P27-008): read-only listing of the shared canvases.
 * Drawing stays in Excalidraw on the web; this surface only navigates into
 * a canvas to hand off. Never a second editor (AGENTS external-engine rule).
 */
final class CanvasScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public array $canvases = [];

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

        $res = KinevoApi::get('/canvases');

        if ($res->successful()) {
            $this->canvases = $res->json('canvases') ?? [];
            $this->state = 'ready';
        } elseif ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';
        } else {
            $this->state = 'error';
            $this->error = 'Could not load canvases.';
        }
    }

    public function openDetail(int $canvasId): void
    {
        $this->navigate('/canvases/'.$canvasId);
    }

    public function render(): View
    {
        return view('canvases', ['screen' => $this]);
    }
}
