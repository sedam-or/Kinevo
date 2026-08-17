<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgramController;
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
});
