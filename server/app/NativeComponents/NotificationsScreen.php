<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Notifications (P27-010): read-path list + per-item mark-read from the
 * shared /notifications contract. Payloads are privacy-preserving (no note
 * content or prompts); a reconciliation notification carrying a task_id deep
 * links straight into the authenticated task detail. Push delivery remains
 * out of scope (needs a provider).
 */
final class NotificationsScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

    public string $notice = '';

    public array $items = [];

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

        $res = KinevoApi::get('/notifications', ['unread' => 1, 'limit' => 50]);

        if ($res->successful()) {
            $this->items = $res->json('notifications') ?? [];
            $this->state = 'ready';
        } elseif ($res->status() === 401) {
            KinevoApi::logout();
            $this->state = 'unauthorized';
        } else {
            $this->state = 'error';
            $this->error = 'Could not load notifications.';
        }
    }

    public function markRead(int $id): void
    {
        $res = KinevoApi::post('/notifications/'.$id.'/read');
        if ($res->successful()) {
            $this->notice = 'Marked as read.';
        }
        $this->reload();
    }

    /**
     * Deep-link a notification to its authenticated target when the payload
     * carries one. Unknown shapes surface a web hint instead of guessing.
     */
    public function open(string $type, int $taskId = 0): void
    {
        if ($type === 'reconciliation' && $taskId > 0) {
            $this->navigate('/tasks/'.$taskId);

            return;
        }

        if ($type === 'break_end') {
            $this->navigate('/');

            return;
        }

        $this->notice = 'This notification opens in the web app.';
    }

    public function render(): View
    {
        return view('notifications', ['screen' => $this]);
    }
}
