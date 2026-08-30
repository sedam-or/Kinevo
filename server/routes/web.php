<?php

use App\Models\Canvas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app'); // UI-022 experiment B
});

// The single-page application shell (TASK-100/101). Mounts the Vue app at #app.
Route::get('/app', function () {
    return view('app');
});

// Development-only canvas browser diagnostic mode (design.md §36).
// Disabled/protected in production: the handler refuses to serve the page
// unless running in a non-production environment, so the route can never leak
// diagnostics into a production deployment (rescue R4 hardening).
Route::get('/dev/canvas-diagnostics', function () {
    if (app()->environment('production')) {
        abort(404);
    }
    $dbUp = false;
    $canvasCount = null;
    try {
        DB::select('select 1');
        $dbUp = true;
        $canvasCount = Canvas::count();
    } catch (Throwable) {
        $dbUp = false;
    }

    return view('dev.canvas-diagnostics', [
        'env' => app()->environment(),
        'dbUp' => $dbUp,
        'canvasCount' => $canvasCount,
    ]);
});

// P27 — NativePHP Android shell: bottom-nav screens rendered by the embedded
// runtime. Registered in a separate file so web-only deploys can drop it.
// Each screen is a Route::native component (resources/views/native/*).
require __DIR__.'/native.php';
