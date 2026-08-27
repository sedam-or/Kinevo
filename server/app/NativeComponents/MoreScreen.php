<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/**
 * More (P27-001 shell tab): review/notifications entry + app info +
 * session control. No dead ends — every item lands on a real surface.
 */
final class MoreScreen extends BaseScreen
{
    public string $appVersion = '0.1.0';

    public bool $authed = false;

    public function mount(): void
    {
        $this->authed = KinevoApi::authed();
        $this->appVersion = (string) config('native.app_version', '0.1.0');
        $this->refreshOffline();
    }

    public function signOut(): void
    {
        KinevoApi::logout();
        $this->authed = false;
    }

    public function render(): View
    {
        return view('more', ['screen' => $this]);
    }
}
