<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LearnerInsightsController;
use App\Http\Controllers\Api\V1\LearnerController;
use App\Http\Controllers\Api\V1\ParentLearnerLinkController;
use App\Http\Controllers\LearningController;
use App\Http\Middleware\ApiTokenAuth;
use Illuminate\Support\Facades\Route;

Route::post('/learning/generate', [LearningController::class, 'generate']);
Route::post('/learning/attempt', [LearningController::class, 'attempt']);

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(ApiTokenAuth::class)->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::post('/learners', [LearnerController::class, 'store']);
        Route::get('/learners/{learner}', [LearnerController::class, 'show']);
        Route::patch('/learners/{learner}', [LearnerController::class, 'update']);
        Route::get('/learners/{learner}/progress', [LearnerInsightsController::class, 'progress']);
        Route::get('/learners/{learner}/reports/weekly', [LearnerInsightsController::class, 'weeklyReport']);

        Route::post('/parents/{parent}/learners/{learner}/link', [ParentLearnerLinkController::class, 'store']);
        Route::get('/parents/{parent}/learners', [ParentLearnerLinkController::class, 'index']);
    });
});
