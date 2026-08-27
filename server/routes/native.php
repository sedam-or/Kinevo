<?php

use App\NativeComponents\CanvasScreen;
use App\NativeComponents\CaptureScreen;
use App\NativeComponents\GoalScreen;
use App\NativeComponents\MoreScreen;
use App\NativeComponents\NotesScreen;
use App\NativeComponents\NotificationsScreen;
use App\NativeComponents\ReviewScreen;
use App\NativeComponents\TasksScreen;
use App\NativeComponents\TodayScreen;
use App\NativeComponents\WorkspacesScreen;
use Illuminate\Support\Facades\Route;

// P27 — Mobile shell (capture/decide/execute/review companion, §12 IA):
// Today · Tasks · Capture · Workspace · More, plus the P27 gate screens
// (Goal, Review, Notifications, Canvas companion). Each is a Route::native
// screen rendered through the embedded NativePHP runtime on Android. The
// browser NEVER sees these when the native package is absent from a deploy.
Route::native('/', TodayScreen::class);
Route::native('/tasks', TasksScreen::class);
Route::native('/capture', CaptureScreen::class);
Route::native('/goals', GoalScreen::class);
Route::native('/review', ReviewScreen::class);
Route::native('/notifications', NotificationsScreen::class);
Route::native('/canvases', CanvasScreen::class);
Route::native('/notes', NotesScreen::class);
Route::native('/workspaces', WorkspacesScreen::class);
Route::native('/more', MoreScreen::class);
