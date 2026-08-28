<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Quick Capture (P27-003): capture a task into the shared pipeline via
 * POST /quick-capture, or a knowledge note via POST /notes. Task capture
 * carries an operation_id (offline envelope) and surfaces queued/saved/
 * error states instead of failing silently. Note capture follows the
 * /notes contract (online only — notes are outside the task offline queue
 * scope, docs/offline-sync.md).
 */
final class CaptureScreen extends BaseScreen
{
    public string $status = 'idle'; // idle|saving|saved|queued|error

    public string $message = '';

    /** Free-text draft (P27-003) — bound to the native text field via setTitle(). */
    public string $draftTitle = '';

    public function mount(): void
    {
        $this->refreshOffline();
        if (! KinevoApi::authed()) {
            $this->status = 'error';
            $this->message = 'Sign in on the Today tab to capture.';
        }
    }

    /** Native text field change → store the draft on the component. */
    public function setTitle(string $title): void
    {
        $this->draftTitle = trim($title);
    }

    /** Capture the typed draft (falls back to the semantic quick action). */
    public function captureDraft(): void
    {
        $this->capture($this->draftTitle !== '' ? $this->draftTitle : 'Plan tomorrow');
        if ($this->status === 'saved') {
            $this->draftTitle = '';
        }
    }

    public function captureNow(): void
    {
        $this->capture('Plan tomorrow');
    }

    public function captureReview(): void
    {
        $this->capture('Review the week');
    }

    /** Capture a reading note as a knowledge note (POST /notes). */
    public function captureNote(): void
    {
        $this->refreshOffline();
        if (! KinevoApi::authed()) {
            $this->status = 'error';
            $this->message = 'Sign in on the Today tab first.';

            return;
        }
        if ($this->offline) {
            $this->status = 'error';
            $this->message = 'Offline — note capture needs a connection.';

            return;
        }

        $this->status = 'saving';

        $res = KinevoApi::post('/notes', [
            'title' => $this->draftTitle !== '' ? $this->draftTitle : 'Reading note',
        ]);

        if ($res->successful()) {
            $this->status = 'saved';
            $this->message = 'Note saved ✓';
            $this->draftTitle = '';
        } elseif ($res->status() === 401) {
            KinevoApi::logout();
            $this->status = 'error';
            $this->message = 'Session expired — sign in again.';
        } elseif ($res->status() === 422) {
            $this->status = 'error';
            $this->message = 'Invalid note: '.implode(' ', collect($res->json('errors'))->flatten()->all());
        } else {
            $this->status = 'error';
            $this->message = 'Note capture failed — retry.';
        }
    }

    private function capture(string $title): void
    {
        $this->refreshOffline();
        if (! KinevoApi::authed()) {
            $this->status = 'error';
            $this->message = 'Sign in on the Today tab first.';

            return;
        }
        if ($this->offline) {
            $this->status = 'queued';
            $this->message = 'Offline — capture queued for sync.';

            return;
        }

        $this->status = 'saving';

        $res = KinevoApi::post('/quick-capture', [
            'title' => $title,
            'source' => 'android',
            'operation_id' => KinevoApi::operationId(),
        ]);

        if ($res->successful()) {
            $this->status = 'saved';
            $this->message = 'Captured ✓';
        } elseif ($res->status() === 401) {
            KinevoApi::logout();
            $this->status = 'error';
            $this->message = 'Session expired — sign in again.';
        } elseif ($res->status() === 422) {
            $this->status = 'error';
            $this->message = 'Invalid capture: '.implode(' ', collect($res->json('errors'))->flatten()->all());
        } else {
            $this->status = 'error';
            $this->message = 'Capture failed — retry.';
        }
    }

    public function render(): View
    {
        return view('capture', ['screen' => $this]);
    }
}
