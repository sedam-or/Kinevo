<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

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

    Route::get('/notes', [NoteController::class, 'index']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::get('/notes/{noteId}', [NoteController::class, 'show']);
    Route::patch('/notes/{noteId}', [NoteController::class, 'update']);
});
