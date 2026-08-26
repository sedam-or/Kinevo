<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdaptiveContextController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BoostController;
use App\Http\Controllers\Api\BreakController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CanvasController;
use App\Http\Controllers\Api\EmergencyPauseController;
use App\Http\Controllers\Api\ExecutionController;
use App\Http\Controllers\Api\FocusSessionController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\HardLandscapeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IcalImportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\KnowledgeLinkController;
use App\Http\Controllers\Api\KnowledgeSearchController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\MiniPauseController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ProgressEventController;
use App\Http\Controllers\Api\RechargeController;
use App\Http\Controllers\Api\RecoveryController;
use App\Http\Controllers\Api\SaasController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ScheduleDraftController;
use App\Http\Controllers\Api\ScheduleExportController;
use App\Http\Controllers\Api\ScheduleOverrideController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TodayController;
use App\Http\Controllers\Api\WeekController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

// TASK-P22-002 — brute-force defense (5/min per IP on credential endpoints).
// TASK-P24-013 — provider webhook (machine-to-machine; signature-verified).
Route::post('/billing/webhook/midtrans', [BillingController::class, 'midtransWebhook'])
    ->middleware('throttle:60,1');

Route::middleware(['guest', 'throttle:auth'])->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Public readiness probe for the reverse proxy / orchestrator (SRS §16.3/§16.5).
Route::get('/health', [HealthController::class, 'health']);

// TASK-P22-005 — normal API class (120/min per user) on every authed route.
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/goals', [GoalController::class, 'index']);
    Route::post('/goals', [GoalController::class, 'store']);
    Route::get('/goals/{goalId}', [GoalController::class, 'show']);
    Route::put('/goals/{goalId}', [GoalController::class, 'update']);
    Route::post('/goals/{goalId}/status', [GoalController::class, 'status']);
    Route::post('/goals/{goalId}/breakdown-proposals', [GoalController::class, 'breakdown'])->middleware('throttle:ai');

    Route::get('/goals/{goalId}/milestones', [MilestoneController::class, 'index']);
    Route::post('/goals/{goalId}/milestones', [MilestoneController::class, 'store']);
    Route::post('/goals/{goalId}/milestones/reorder', [MilestoneController::class, 'reorder']);
    Route::get('/goals/{goalId}/milestones/{milestoneId}', [MilestoneController::class, 'show']);
    Route::put('/goals/{goalId}/milestones/{milestoneId}', [MilestoneController::class, 'update']);
    Route::post('/goals/{goalId}/milestones/{milestoneId}/status', [MilestoneController::class, 'status']);

    Route::get('/programs', [ProgramController::class, 'index']);

    // TASK-P23-008 — plan overview + self-serve switching (manual provider).
    Route::get('/saas/plan', [SaasController::class, 'show']);
    Route::patch('/saas/plan', [SaasController::class, 'update']);
    Route::put('/saas/plan', [SaasController::class, 'update']);

    // TASK-P24-010 — checkout creation (idempotent, authenticated).
    Route::post('/billing/cancel', [BillingController::class, 'cancel']);
    Route::post('/billing/resume', [BillingController::class, 'resume']);
    Route::post('/billing/checkout', [BillingController::class, 'checkout']);
    Route::get('/billing/subscription', [BillingController::class, 'subscription']);

    // TASK-P19-004 — Workspace control plane (owner-scoped).
    Route::get('/workspaces', [WorkspaceController::class, 'index']);
    Route::post('/workspaces', [WorkspaceController::class, 'store']);
    Route::get('/workspaces/{workspaceId}', [WorkspaceController::class, 'show']);
    Route::patch('/workspaces/{workspaceId}', [WorkspaceController::class, 'update']);
    Route::put('/workspaces/{workspaceId}', [WorkspaceController::class, 'update']);
    Route::delete('/workspaces/{workspaceId}/archive', [WorkspaceController::class, 'archive']);
    Route::post('/workspaces/{workspaceId}/restore', [WorkspaceController::class, 'restore']);
    Route::post('/workspaces/{workspaceId}/default', [WorkspaceController::class, 'setDefault']);

    Route::post('/programs', [ProgramController::class, 'store']);
    Route::get('/programs/{programId}', [ProgramController::class, 'show']);
    Route::put('/programs/{programId}', [ProgramController::class, 'update']);
    Route::post('/programs/{programId}/status', [ProgramController::class, 'status']);

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::post('/quick-capture', [TaskController::class, 'quickCapture']);
    Route::get('/tasks/{taskId}', [TaskController::class, 'show']);
    Route::put('/tasks/{taskId}', [TaskController::class, 'update']);
    Route::post('/tasks/{taskId}/auto-swap', [TaskController::class, 'autoSwap']);
    Route::post('/tasks/{taskId}/status', [TaskController::class, 'status']);
    Route::post('/tasks/{taskId}/partial-complete', [TaskController::class, 'partialComplete']);
    Route::get('/tasks/{taskId}/subtasks', [TaskController::class, 'subtasks']);
    Route::post('/tasks/{taskId}/subtasks', [TaskController::class, 'addSubtask']);
    Route::post('/tasks/{taskId}/subtasks/{subtaskId}/toggle', [TaskController::class, 'toggleSubtask']);
    Route::put('/tasks/{taskId}/subtasks/{subtaskId}', [TaskController::class, 'updateSubtask']);
    Route::post('/subtasks/{subtaskId}/promote', [TaskController::class, 'promoteSubtask']);

    // Evidence attachments (FR-43): max 3 JPG/PNG/PDF per completed task, ≤5 MB.
    Route::get('/attachments/rules', [AttachmentController::class, 'rules']);
    Route::get('/tasks/{taskId}/attachments', [AttachmentController::class, 'index']);
    Route::post('/tasks/{taskId}/attachments', [AttachmentController::class, 'store'])->middleware('throttle:uploads');
    Route::get('/tasks/{taskId}/attachments/{attachmentId}', [AttachmentController::class, 'show']);
    Route::delete('/tasks/{taskId}/attachments/{attachmentId}', [AttachmentController::class, 'destroy']);

    // KRS PDF import (FR-24): stage → preview → confirm to persist Hard Landscape.
    Route::post('/imports/krs-pdf', [ImportController::class, 'store'])->middleware('throttle:uploads');
    Route::get('/imports/{importId}', [ImportController::class, 'show']);
    Route::post('/imports/{importId}/confirm', [ImportController::class, 'confirm']);
    Route::post('/imports/{importId}/discard', [ImportController::class, 'discard']);

    // iCalendar import (FR-30): stage → preview → confirm; conflicts never overwrite.
    Route::post('/imports/ics', [IcalImportController::class, 'store'])->middleware('throttle:uploads');
    Route::get('/imports/ics/{importId}', [IcalImportController::class, 'show']);
    Route::post('/imports/ics/{importId}/confirm', [IcalImportController::class, 'confirm']);
    Route::post('/imports/ics/{importId}/discard', [IcalImportController::class, 'discard']);

    Route::get('/logs', [ActivityLogController::class, 'index']);
    Route::post('/export', [ActivityLogController::class, 'export'])->middleware('throttle:exports');

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'read']);

    Route::get('/recovery', [RecoveryController::class, 'index']);
    Route::post('/recovery/{taskId}', [RecoveryController::class, 'resolve']);

    // Recharge Timer (FR-05, TASK-121) — persisted timer + Work-Life Ratio.
    Route::get('/recharge/status', [RechargeController::class, 'status']);
    Route::get('/recharge', [RechargeController::class, 'index']);
    Route::post('/recharge/start', [RechargeController::class, 'start']);
    Route::post('/recharge/{sessionId}/pause', [RechargeController::class, 'pause']);
    Route::post('/recharge/{sessionId}/resume', [RechargeController::class, 'resume']);
    Route::post('/recharge/{sessionId}/complete', [RechargeController::class, 'complete']);
    Route::post('/recharge/{sessionId}/abandon', [RechargeController::class, 'abandon']);

    Route::get('/adaptive/context', [AdaptiveContextController::class, 'index']);
    Route::post('/adaptive/context', [AdaptiveContextController::class, 'store']);
    Route::get('/adaptive/burnout', [AdaptiveContextController::class, 'burnout']);

    Route::get('/focus-sessions', [FocusSessionController::class, 'index']);
    Route::post('/focus-sessions', [FocusSessionController::class, 'store']);
    Route::get('/focus-sessions/recommendation', [FocusSessionController::class, 'recommendation']);

    // Execution Timer (FR-05, TASK-120) — persisted, server-derived timer state.
    Route::get('/execution/active', [ExecutionController::class, 'active']);
    Route::get('/execution', [ExecutionController::class, 'index']);
    Route::post('/execution/start', [ExecutionController::class, 'start']);
    Route::post('/execution/{sessionId}/pause', [ExecutionController::class, 'pause']);
    Route::post('/execution/{sessionId}/resume', [ExecutionController::class, 'resume']);
    Route::post('/execution/{sessionId}/complete', [ExecutionController::class, 'complete']);
    Route::post('/execution/{sessionId}/abandon', [ExecutionController::class, 'abandon']);

    Route::get('/progress', [ProgressEventController::class, 'index']);
    Route::post('/progress', [ProgressEventController::class, 'store']);

    Route::get('/ai/status', [AiController::class, 'status']);
    Route::get('/ai/config', [AiController::class, 'configShow']);
    Route::put('/ai/config', [AiController::class, 'configUpdate']);
    Route::post('/ai/config/test', [AiController::class, 'configTest']);
    // TASK-P18-006 — canonical AI settings control-plane endpoints.
    Route::get('/ai/settings', [AiController::class, 'settingsShow']);
    Route::match(['patch', 'put'], '/ai/settings', [AiController::class, 'settingsUpdate']);
    Route::post('/ai/settings/credential', [AiController::class, 'credentialSet']);
    Route::delete('/ai/settings/credential', [AiController::class, 'credentialRemove']);
    Route::post('/ai/settings/test', [AiController::class, 'settingsTest'])->middleware('throttle:ai');
    Route::post('/ai/settings/enable', [AiController::class, 'settingsEnable']);
    Route::post('/ai/settings/disable', [AiController::class, 'settingsDisable']);
    Route::get('/ai/providers', [AiController::class, 'providersIndex']);
    Route::post('/ai/generate', [AiController::class, 'generate'])->middleware('throttle:ai');
    Route::post('/ai/proposals', [AiController::class, 'proposals'])->middleware('throttle:ai');
    Route::get('/ai/proposals', [AiController::class, 'proposalsIndex']);
    Route::get('/ai/proposals/{proposalId}', [AiController::class, 'proposalsShow']);
    Route::put('/ai/proposals/{proposalId}', [AiController::class, 'proposalsUpdate']);
    Route::post('/ai/proposals/{proposalId}/accept', [AiController::class, 'proposalsAccept']);
    Route::post('/ai/proposals/{proposalId}/reject', [AiController::class, 'proposalsReject']);
    Route::post('/ai/summarize-note', [AiController::class, 'summarizeNote'])->middleware('throttle:ai');
    Route::post('/ai/extract-tasks', [AiController::class, 'extractTasks'])->middleware('throttle:ai');
    Route::post('/ai/suggest-canvas', [AiController::class, 'suggestCanvas'])->middleware('throttle:ai');
    Route::get('/ai/runs', [AiController::class, 'runs']);

    // TASK-P25-008 — per-user BYOK provider settings (custom_provider-gated).
    Route::get('/ai/byok', [AiController::class, 'byokShow']);
    Route::put('/ai/byok', [AiController::class, 'byokSave']);
    Route::delete('/ai/byok', [AiController::class, 'byokDelete']);

    Route::get('/metrics', [HealthController::class, 'metrics']);
    Route::get('/observability/runs', [HealthController::class, 'runs']);

    Route::get('/notes', [NoteController::class, 'index']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::get('/notes/{noteId}', [NoteController::class, 'show']);
    Route::patch('/notes/{noteId}', [NoteController::class, 'update']);

    Route::get('/notes/{noteId}/links', [KnowledgeLinkController::class, 'index']);
    Route::post('/notes/{noteId}/links', [KnowledgeLinkController::class, 'store']);
    Route::delete('/notes/{noteId}/links/{linkId}', [KnowledgeLinkController::class, 'destroy']);
    Route::get('/canvases/{canvasId}/links', [KnowledgeLinkController::class, 'canvasIndex']);
    Route::post('/canvases/{canvasId}/links', [KnowledgeLinkController::class, 'canvasStore']);
    Route::delete('/canvases/{canvasId}/links/{linkId}', [KnowledgeLinkController::class, 'canvasDestroy']);
    Route::get('/knowledge/links', [KnowledgeLinkController::class, 'byTarget']);
    Route::get('/knowledge/search', [KnowledgeSearchController::class, 'search']);

    Route::get('/canvases', [CanvasController::class, 'index']);
    Route::post('/canvases', [CanvasController::class, 'store']);
    Route::get('/canvases/{canvasId}', [CanvasController::class, 'show']);
    Route::put('/canvases/{canvasId}', [CanvasController::class, 'save']);
    Route::patch('/canvases/{canvasId}', [CanvasController::class, 'rename']);
    Route::post('/canvases/{canvasId}/archive', [CanvasController::class, 'archive']);
    Route::get('/canvases/{canvasId}/files', [CanvasController::class, 'files']);
    Route::post('/canvases/{canvasId}/files', [CanvasController::class, 'addFile']);

    // Schedule queries (FR-01/FR-11/FR-15; SRS §8.2) — read-only.
    Route::get('/today', [TodayController::class, 'index']);
    Route::get('/schedule', [ScheduleController::class, 'index']);
    Route::get('/week', [WeekController::class, 'index']);
    Route::get('/calendar', [CalendarController::class, 'index']);

    // iCalendar export (FR-30 / TASK-143): download selected schedule range.
    Route::get('/schedule/export/ics', [ScheduleExportController::class, 'ical'])->middleware('throttle:exports');

    // Hard Landscape (SRS §7.1; FR-27/FR-28).
    Route::get('/hard-landscape', [HardLandscapeController::class, 'index']);
    Route::post('/hard-landscape', [HardLandscapeController::class, 'store']);
    Route::get('/hard-landscape/{eventId}', [HardLandscapeController::class, 'show']);
    Route::patch('/hard-landscape/{eventId}', [HardLandscapeController::class, 'update']);
    Route::delete('/hard-landscape/{eventId}', [HardLandscapeController::class, 'destroy']);

    // Schedule Draft / Dynamic Rescheduler (FR-27/FR-28; SRS §7.1).
    Route::post('/schedule/draft', [ScheduleDraftController::class, 'draft']);
    Route::post('/schedule/draft/apply', [ScheduleDraftController::class, 'apply']);
    Route::post('/schedule/reschedule', [ScheduleDraftController::class, 'reschedule']);
    Route::post('/schedule/reschedule/apply', [ScheduleDraftController::class, 'rescheduleApply']);

    // Mini Pause (FR-07): move all eligible today tasks to the next day.
    Route::post('/schedule/mini-pause', [MiniPauseController::class, 'store']);

    // Emergency Pause (FR-07): tag the week exceptional, keep selected tasks,
    // shift all other eligible tasks +1 week.
    Route::post('/schedule/emergency-pause', [EmergencyPauseController::class, 'store']);

    // Break Mode (FR-36/FR-39): confirm a manual break period and end it.
    Route::post('/break', [BreakController::class, 'store']);
    Route::post('/break/end', [BreakController::class, 'end']);

    // Boost Mode (FR-37/FR-38): show setup + recommendation, save/end a target.
    Route::get('/boost', [BoostController::class, 'index']);
    Route::post('/boost', [BoostController::class, 'store']);
    Route::post('/boost/end', [BoostController::class, 'end']);

    // Analytics (Phase 13): consume already-generated data.
    Route::get('/analytics/work-life', [AnalyticsController::class, 'workLife']);
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/pillars', [AnalyticsController::class, 'pillars']);
    Route::get('/analytics/heatmap', [AnalyticsController::class, 'heatmap']);

    // Schedule Overrides (FR-25; SRS §7.1).
    Route::get('/schedule-overrides', [ScheduleOverrideController::class, 'index']);
    Route::post('/schedule-overrides', [ScheduleOverrideController::class, 'store']);
    Route::get('/schedule-overrides/{overrideId}', [ScheduleOverrideController::class, 'show']);
    Route::patch('/schedule-overrides/{overrideId}', [ScheduleOverrideController::class, 'update']);
    Route::delete('/schedule-overrides/{overrideId}', [ScheduleOverrideController::class, 'destroy']);
});
