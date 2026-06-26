<?php

use App\Http\Controllers\AIHRController;
use App\Http\Controllers\CodeReviewController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\KafkaJobController;
use App\Http\Controllers\SeniorProgramController;
use App\Http\Controllers\TestAssessmentController;
use App\Http\Controllers\WorkspaceLessonController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/main');
    }

    return Inertia::render('Welcome');
})->name('landing');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/main', [CourseController::class, 'index'])->name('main');
});

Route::get('/courses/{course}/settings', [CourseController::class, 'settings'])->name('course.settings');
Route::put('/courses/{course}/settings', [CourseController::class, 'updateSettings'])->name('course.settings.update');
Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('course.destroy');

Route::get('/create-course', [CourseController::class, 'create'])->name('create.course');

Route::get('/test', [TestAssessmentController::class, 'show'])->name('test.show');
Route::post('/test/prepare-retake', [TestAssessmentController::class, 'prepareRetake'])->name('test.prepare-retake');
Route::post('/test/start', [TestAssessmentController::class, 'startAdaptiveTest'])->name('test.start');
Route::get('/test/session/{sessionId}', [TestAssessmentController::class, 'sessionState'])->name('test.session');
Route::post('/test/advance', [TestAssessmentController::class, 'advance'])->name('test.advance');
Route::post('/test/submit-answer', [TestAssessmentController::class, 'submitAnswer'])->name('test.submit');
Route::post('/test/complete-task', [TestAssessmentController::class, 'completeTask'])->name('test.complete');

Route::get('/plan-settings', [CourseController::class, 'planSettings'])->name('plan.settings');
Route::post('/plan/select-level', [CourseController::class, 'storePlanSelection'])->name('plan.select-level');

Route::get('/senior-program', [SeniorProgramController::class, 'hub'])->name('senior.hub');

Route::get('/onboarding', [CourseController::class, 'onboarding'])->name('onboarding');
Route::post('/generate-plan', [CourseController::class, 'generatePlan'])->name('plan.generate');

Route::get('/workspace/{course}', [CourseController::class, 'workspace'])->name('workspace');

Route::prefix('workspace')->group(function () {
    Route::post('/lesson/start', [WorkspaceLessonController::class, 'start']);
    Route::post('/lesson/review', [WorkspaceLessonController::class, 'review']);
    Route::post('/lesson/generate', [WorkspaceLessonController::class, 'generate']);
    Route::post('/lesson/status', [WorkspaceLessonController::class, 'status']);
    Route::post('/lesson/continue', [WorkspaceLessonController::class, 'continue']);
    Route::post('/lesson/submit', [WorkspaceLessonController::class, 'submit']);
    Route::post('/lesson/hint', [WorkspaceLessonController::class, 'hint']);
    Route::post('/lesson/job', [WorkspaceLessonController::class, 'job']);
    Route::post('/lesson/draft', [WorkspaceLessonController::class, 'saveDraft']);
    Route::post('/mentor/help', [WorkspaceLessonController::class, 'mentorHelp']);
    Route::post('/behavior', [WorkspaceLessonController::class, 'trackBehavior']);
    Route::post('/profile', [WorkspaceLessonController::class, 'updateProfile']);
});

Route::get('/progress', [ProgressController::class, 'index'])->name('progress');

Route::get('/code-review', [CodeReviewController::class, 'index'])->name('code-review');
Route::post('/code-review/analyze', [CodeReviewController::class, 'analyze'])->name('code-review.analyze');

Route::post('/kafka/job', [KafkaJobController::class, 'poll'])->name('kafka.job');

Route::get('/ai-hr', [AIHRController::class, 'index'])->name('ai-hr');
Route::prefix('ai-hr')->name('ai-hr.')->group(function () {
    Route::post('/start', [AIHRController::class, 'start'])->name('start');
    Route::post('/reply', [AIHRController::class, 'reply'])->name('reply');
    Route::post('/code', [AIHRController::class, 'submitCode'])->name('code');
    Route::post('/stop', [AIHRController::class, 'stop'])->name('stop');
});


Route::post('/test/force-senior-preview', [TestAssessmentController::class, 'forceSeniorPreview'])
    ->name('test.force-senior-preview');

Route::get('/welcome', function () {
    return Inertia::render('Welcome');
})->name('welcome');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
