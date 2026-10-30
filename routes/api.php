<?php

use App\Http\Controllers\Api\Admin\AdminCatalogController;
use App\Http\Controllers\Api\Admin\ChapterAdminController;
use App\Http\Controllers\Api\Admin\QuestionAdminController;
use App\Http\Controllers\Api\Admin\ResourceAdminController;
use App\Http\Controllers\Api\Admin\TestBlueprintAdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\SyllabusController;
use App\Http\Controllers\Api\StudentWorkspaceController;
use App\Http\Controllers\Api\TestAttemptController;
use App\Http\Controllers\Api\TestGenerationController;
use App\Http\Controllers\Api\WeightageController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::get('/syllabus', [SyllabusController::class, 'index']);
Route::get('/resources', [ResourceController::class, 'index']);
Route::get('/questions', [QuestionController::class, 'index']);
Route::get('/weightage', [WeightageController::class, 'index']);
Route::get('/test-blueprints', [TestGenerationController::class, 'blueprints']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/student/workspace', [StudentWorkspaceController::class, 'show']);
    Route::patch('/student/workspace', [StudentWorkspaceController::class, 'update']);
    Route::post('/questions/{question}/answer', [QuestionController::class, 'answer'])->middleware('throttle:60,1');
    Route::post('/tests/generate', [TestGenerationController::class, 'generate']);
    Route::post('/tests/{test}/submit', [TestAttemptController::class, 'submit']);
    Route::get('/attempts', [TestAttemptController::class, 'index']);

    Route::middleware(EnsureAdmin::class)->prefix('admin')->group(function () {
        Route::get('/questions', [QuestionAdminController::class, 'index']);
        Route::post('/questions', [QuestionAdminController::class, 'store']);
        Route::put('/questions/{question}', [QuestionAdminController::class, 'update']);
        Route::delete('/questions/{question}', [QuestionAdminController::class, 'destroy']);
        Route::post('/questions/import', [QuestionAdminController::class, 'import']);
        Route::patch('/questions/{question}/review', [QuestionAdminController::class, 'review']);
        Route::patch('/chapters/{chapter}/weightage', [ChapterAdminController::class, 'updateWeightage']);
        Route::post('/chapters/{chapter}/topics', [ChapterAdminController::class, 'addTopic']);
        Route::put('/topics/{topic}', [ChapterAdminController::class, 'updateTopic']);
        Route::delete('/topics/{topic}', [ChapterAdminController::class, 'deleteTopic']);
        Route::get('/resources', [AdminCatalogController::class, 'resources']);
        Route::post('/resources', [ResourceAdminController::class, 'store']);
        Route::put('/resources/{resource}', [ResourceAdminController::class, 'update']);
        Route::delete('/resources/{resource}', [ResourceAdminController::class, 'destroy']);
        Route::get('/test-blueprints', [TestBlueprintAdminController::class, 'index']);
        Route::post('/test-blueprints', [TestBlueprintAdminController::class, 'store']);
        Route::put('/test-blueprints/{blueprint}', [TestBlueprintAdminController::class, 'update']);
    });
});
