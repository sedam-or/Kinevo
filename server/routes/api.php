<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdaptiveContextController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CanvasController;
use App\Http\Controllers\Api\FocusSessionController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\HardLandscapeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\KnowledgeLinkController;
use App\Http\Controllers\Api\KnowledgeSearchController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ProgressEventController;
use App\Http\Controllers\Api\RecoveryController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TodayController;
use App\Http\Controllers\Api\WeekController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Public readiness probe for the reverse proxy / orchestrator (SRS §16.3/§16.5).
Route::get('/health', [HealthController::class, 'health']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/goals', [GoalController::class, 'index']);
    Route::post('/goals', [GoalController::class, 'store']);
    Route::get('/goals/{goalId}', [GoalController::class, 'show']);
    Route::put('/goals/{goalId}', [GoalController::class, 'update']);
    Route::post('/goals/{goalId}/status', [GoalController::class, 'status']);
    Route::post('/goals/{goalId}/breakdown-proposals', [GoalController::class, 'breakdown']);

    Route::get('/goals/{goalId}/milestones', [MilestoneController::class, 'index']);
    Route::post('/goals/{goalId}/milestones', [MilestoneController::class, 'store']);
    Route::post('/goals/{goalId}/milestones/reorder', [MilestoneController::class, 'reorder']);
    Route::get('/goals/{goalId}/milestones/{milestoneId}', [MilestoneController::class, 'show']);
    Route::put('/goals/{goalId}/milestones/{milestoneId}', [MilestoneController::class, 'update']);
    Route::post('/goals/{goalId}/milestones/{milestoneId}/status', [MilestoneController::class, 'status']);

    Route::get('/programs', [ProgramController::class, 'index']);
    Route::post('/programs', [ProgramController::class, 'store']);
    Route::get('/programs/{programId}', [ProgramController::class, 'show']);
    Route::put('/programs/{programId}', [ProgramController::class, 'update']);
    Route::post('/programs/{programId}/status', [ProgramController::class, 'status']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{taskId}', [TaskController::class, 'show']);
    Route::put('/tasks/{taskId}', [TaskController::class, 'update']);
    Route::post('/tasks/{taskId}/status', [TaskController::class, 'status']);
    Route::post('/tasks/{taskId}/partial-complete', [TaskController::class, 'partialComplete']);
    Route::get('/tasks/{taskId}/subtasks', [TaskController::class, 'subtasks']);
    Route::post('/tasks/{taskId}/subtasks', [TaskController::class, 'addSubtask']);
    Route::post('/tasks/{taskId}/subtasks/{subtaskId}/toggle', [TaskController::class, 'toggleSubtask']);
    Route::put('/tasks/{taskId}/subtasks/{subtaskId}', [TaskController::class, 'updateSubtask']);
    Route::post('/subtasks/{subtaskId}/promote', [TaskController::class, 'promoteSubtask']);

    Route::get('/logs', [ActivityLogController::class, 'index']);
    Route::post('/export', [ActivityLogController::class, 'export']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'read']);

    Route::get('/recovery', [RecoveryController::class, 'index']);
    Route::post('/recovery/{taskId}', [RecoveryController::class, 'resolve']);

    Route::get('/adaptive/context', [AdaptiveContextController::class, 'index']);
    Route::post('/adaptive/context', [AdaptiveContextController::class, 'store']);
    Route::get('/adaptive/burnout', [AdaptiveContextController::class, 'burnout']);

    Route::get('/focus-sessions', [FocusSessionController::class, 'index']);
    Route::post('/focus-sessions', [FocusSessionController::class, 'store']);
    Route::get('/focus-sessions/recommendation', [FocusSessionController::class, 'recommendation']);

    Route::get('/progress', [ProgressEventController::class, 'index']);
    Route::post('/progress', [ProgressEventController::class, 'store']);

    Route::get('/ai/status', [AiController::class, 'status']);
    Route::post('/ai/generate', [AiController::class, 'generate']);
    Route::post('/ai/proposals', [AiController::class, 'proposals']);
    Route::get('/ai/proposals', [AiController::class, 'proposalsIndex']);
    Route::get('/ai/proposals/{proposalId}', [AiController::class, 'proposalsShow']);
    Route::post('/ai/proposals/{proposalId}/accept', [AiController::class, 'proposalsAccept']);
    Route::post('/ai/proposals/{proposalId}/reject', [AiController::class, 'proposalsReject']);
    Route::post('/ai/summarize-note', [AiController::class, 'summarizeNote']);
    Route::post('/ai/extract-tasks', [AiController::class, 'extractTasks']);
    Route::post('/ai/suggest-canvas', [AiController::class, 'suggestCanvas']);
    Route::get('/ai/runs', [AiController::class, 'runs']);

    Route::get('/metrics', [HealthController::class, 'metrics']);
    Route::get('/observability/runs', [HealthController::class, 'runs']);

    Route::get('/notes', [NoteController::class, 'index']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::get('/notes/{noteId}', [NoteController::class, 'show']);
    Route::patch('/notes/{noteId}', [NoteController::class, 'update']);

    Route::get('/notes/{noteId}/links', [KnowledgeLinkController::class, 'index']);
    Route::post('/notes/{noteId}/links', [KnowledgeLinkController::class, 'store']);
    Route::delete('/notes/{noteId}/links/{linkId}', [KnowledgeLinkController::class, 'destroy']);
    Route::get('/knowledge/links', [KnowledgeLinkController::class, 'byTarget']);
    Route::get('/knowledge/search', [KnowledgeSearchController::class, 'search']);

    Route::get('/canvases', [CanvasController::class, 'index']);
    Route::post('/canvases', [CanvasController::class, 'store']);
    Route::get('/canvases/{canvasId}', [CanvasController::class, 'show']);
    Route::put('/canvases/{canvasId}', [CanvasController::class, 'save']);
    Route::get('/canvases/{canvasId}/files', [CanvasController::class, 'files']);
    Route::post('/canvases/{canvasId}/files', [CanvasController::class, 'addFile']);

    // Schedule queries (FR-01/FR-11/FR-15; SRS §8.2) — read-only.
    Route::get('/today', [TodayController::class, 'index']);
    Route::get('/schedule', [ScheduleController::class, 'index']);
    Route::get('/week', [WeekController::class, 'index']);
    Route::get('/calendar', [CalendarController::class, 'index']);

    // Hard Landscape (SRS §7.1; FR-27/FR-28).
    Route::get('/hard-landscape', [HardLandscapeController::class, 'index']);
    Route::post('/hard-landscape', [HardLandscapeController::class, 'store']);
    Route::get('/hard-landscape/{eventId}', [HardLandscapeController::class, 'show']);
    Route::patch('/hard-landscape/{eventId}', [HardLandscapeController::class, 'update']);
    Route::delete('/hard-landscape/{eventId}', [HardLandscapeController::class, 'destroy']);
});
