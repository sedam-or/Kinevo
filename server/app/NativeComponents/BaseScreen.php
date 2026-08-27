<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Shared mobile shell behavior (P27-001): offline detection + error
 * surface helpers. Screens render their Blade view with the same bottom
 * navigation (Today · Capture · Workspace · More).
 */
abstract class BaseScreen extends NativeComponent
{
    public bool $offline = false;

    public function navTitle(): string
    {
        return 'Kinevo';
    }

    protected function refreshOffline(): void
    {
        $this->offline = ! KinevoApi::health();
    }

    protected function error(string $message): View
    {
        $this->offline = ! KinevoApi::health();

        return view('state-error', ['screen' => $this, 'message' => $message]);
    }
}
