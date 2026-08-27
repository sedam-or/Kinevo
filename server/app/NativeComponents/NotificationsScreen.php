<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * Notifications (P27-009): read-path list + per-item mark-read from the
 * shared /notifications contract. Delivery channels remain in-app only.
 */
final class NotificationsScreen extends BaseScreen
{
    public string $state = 'loading';

    public string $error = '';

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
        KinevoApi::post('/notifications/'.$id.'/read');
        $this->reload();
    }

    public function render(): View
    {
        return view('notifications', ['screen' => $this]);
    }
}
