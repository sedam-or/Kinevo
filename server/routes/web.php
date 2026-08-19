<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// The single-page application shell (TASK-100/101). Mounts the Vue app at #app.
Route::get('/app', function () {
    return view('app');
});
