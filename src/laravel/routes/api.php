<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\CodeReviewApiController;
use App\Http\Controllers\Api\V1\CodeReviewPreviewController;
use App\Http\Controllers\Api\V1\JobApiController;
use App\Http\Controllers\CodeReviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function (): void {
    // Stateless preview для VS Code — без auth и без записи в БД.
    Route::post('/analyze/preview', [CodeReviewPreviewController::class, 'store']);
    Route::get('/jobs/{jobId}', [JobApiController::class, 'show'])
        ->whereUuid('jobId');

    // --- Auth (REST) ---
    Route::post('/tokens', [AuthTokenController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthTokenController::class, 'me']);
        Route::delete('/tokens/current', [AuthTokenController::class, 'destroy']);

        // --- Code reviews (REST resource) ---
        Route::get('/code-reviews', [CodeReviewApiController::class, 'index']);
        Route::post('/code-reviews', [CodeReviewApiController::class, 'store']);
        Route::get('/code-reviews/latest', [CodeReviewApiController::class, 'latest']);
        Route::get('/code-reviews/{uuid}', [CodeReviewApiController::class, 'show'])
            ->whereUuid('uuid');

        // --- Async jobs (auth) — дубликат для REST-клиентов с токеном ---
        Route::get('/auth/jobs/{jobId}', [JobApiController::class, 'show'])
            ->whereUuid('jobId');

        // --- Deprecated RPC-style aliases (VS Code / старые клиенты) ---
        Route::post('/auth/token', [AuthTokenController::class, 'store']);
        Route::get('/auth/me', [AuthTokenController::class, 'me']);
        Route::delete('/auth/token', [AuthTokenController::class, 'destroy']);
        Route::post('/code-review/analyze', [CodeReviewApiController::class, 'analyze']);
        Route::get('/code-review/history', [CodeReviewApiController::class, 'history']);
        Route::get('/code-review/latest', [CodeReviewApiController::class, 'latest']);
        Route::get('/code-review/{uuid}', [CodeReviewApiController::class, 'show'])
            ->whereUuid('uuid');
    });
});

// Legacy web API.
Route::post('/sonar-stats/analyze', [CodeReviewController::class, 'analyze']);
