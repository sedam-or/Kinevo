<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
    try {
        \Illuminate\Support\Facades\DB::select('select 1');
        $dbUp = true;
    } catch (\Throwable) {
        $dbUp = false;
    }
    return view('dev.canvas-diagnostics', [
        'env' => app()->environment(),
        'dbUp' => $dbUp,
        'canvasCount' => $dbUp ? \App\Models\Canvas::count() : null,
    ]);
});
