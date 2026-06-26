<?php

namespace App\Http\Controllers;

use App\Service\CodeAnalysisHelper;
use App\Service\LessonService;
use App\Service\MentorHelpService;
use App\Service\UserBehaviorService;
use App\Service\UserLearningProfileService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** REST для workspace — тонкая обёртка над LessonService. */
class WorkspaceLessonController extends Controller
{
    public function __construct(
        protected LessonService $lessons,
        protected UserBehaviorService $behavior,
        protected MentorHelpService $mentorHelp,
        protected CodeAnalysisHelper $codeAnalysis,
        protected UserLearningProfileService $profiles,
    ) {}

    /** GET текущего слайда/задачи после «Открыть урок». */
    public function start(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'subtopic_id' => 'required|integer|exists:subtopics,id',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        try {
            return response()->json(
                $this->lessons->startLesson($userId, $data['course_id'], $data['subtopic_id'])
            );
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /** Запуск генерации теории (Kafka job или sync). */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'subtopic_id' => 'required|integer|exists:subtopics,id',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        try {
            return response()->json(
                $this->lessons->generateLesson($userId, $data['course_id'], $data['subtopic_id'])
            );
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function status(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'subtopic_id' => 'required|integer|exists:subtopics,id',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        try {
            return response()->json(
                $this->lessons->getLessonStatus($userId, $data['course_id'], $data['subtopic_id'])
            );
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /** Универсальный endpoint: следующий слайд, вопрос ментору, назад к теории. */
    public function continue(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'subtopic_id' => 'required|integer|exists:subtopics,id',
            'action'      => 'required|string|in:continue,question,request_theory,theory_back',
            'message'      => 'nullable|string|max:2000',
            'current_code' => 'nullable|string|max:50000',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        try {
            return response()->json(
                $this->lessons->continueLesson(
                    $userId,
                    $data['course_id'],
                    $data['subtopic_id'],
                    $data['action'],
                    $data['message'] ?? null,
                    $data['current_code'] ?? null,
                )
            );
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    /** Отправка решения практики — Sonar + AI review. */
    public function submit(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'subtopic_id' => 'required|integer|exists:subtopics,id',
            'code'        => 'required|string|max:50000',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        try {
            return response()->json(
                $this->lessons->submitSolution(
                    $userId,
                    $data['course_id'],
                    $data['subtopic_id'],
                    $data['code'],
                )
            );
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function hint(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'subtopic_id' => 'required|integer|exists:subtopics,id',
            'hint_type'    => 'required|string|in:code_snippet,explanation,simpler_solution,skeleton_hint,plantuml',
            'current_code' => 'nullable|string|max:50000',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        try {
            return response()->json(
                $this->lessons->requestHint(
                    $userId,
                    $data['course_id'],
                    $data['subtopic_id'],
                    $data['hint_type'],
                    $data['current_code'] ?? null,
                )
            );
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function job(Request $request): JsonResponse
    {
        $data = $request->validate([
            'job_id' => 'required|uuid',
        ]);

        $userId = $this->resolveUserId();
        if (! $userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        return response()->json($this->lessons->pollJob($data['job_id']));
    }

    public function review(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'subtopic_id' => 'required|integer|exists:subtopics,id',
            'slide_index' => 'nullable|integer|min:0|max:50',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        try {
            return response()->json(
                $this->lessons->theoryReviewSlide(
                    $userId,
                    $data['course_id'],
                    $data['subtopic_id'],
                    (int) ($data['slide_index'] ?? 0),
                )
            );
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function saveDraft(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'subtopic_id' => 'required|integer|exists:subtopics,id',
            'code'        => 'required|string|max:50000',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        try {
            $this->lessons->saveDraftCode($userId, $data['course_id'], $data['subtopic_id'], $data['code']);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function mentorHelp(Request $request)
    {
        $data = $request->validate([
            'course_id'    => 'required|integer|exists:courses,id',
            'subtopic_id'  => 'required|integer|exists:subtopics,id',
            'action'       => 'required|string|in:accept,decline,select_option',
            'option_id'    => 'nullable|string|in:concept,review_code,architecture,first_steps',
            'current_code' => 'nullable|string|max:50000',
            'starter_code' => 'nullable|string|max:50000',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        try {
            $subtopic = \App\Models\Subtopic::with('theme.module.course')->findOrFail($data['subtopic_id']);
            $progress = \App\Models\UserProgressSubtopic::where('user_id', $userId)
                ->where('course_id', $data['course_id'])
                ->where('subtopic_id', $data['subtopic_id'])
                ->firstOrFail();
            $profile = $this->profiles->getProfile($userId, $data['course_id']);

            if ($data['action'] === 'decline') {
                return response()->json([
                    'block' => [
                        'type'    => 'mentor',
                        'message' => 'Хорошо, продолжайте в своём темпе. Если понадобится помощь — напишите в чат.',
                    ],
                ]);
            }

            if ($data['action'] === 'accept') {
                $hasCode = $this->codeAnalysis->hasMeaningfulCode(
                    $data['current_code'] ?? null,
                    $data['starter_code'] ?? null,
                );

                return response()->json([
                    'block' => $this->mentorHelp->menuBlock($hasCode),
                ]);
            }

            $optionId = $data['option_id'] ?? 'concept';
            $block = $this->mentorHelp->generateOptionHelp(
                $subtopic,
                $progress,
                $profile,
                $data['course_id'],
                $userId,
                $optionId,
                $data['current_code'] ?? null,
                $data['starter_code'] ?? null,
            );

            return response()->json(['block' => $block]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function trackBehavior(Request $request)
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'subtopic_id' => 'nullable|integer|exists:subtopics,id',
            'event_type'  => 'required|string|in:tab_return,undo_burst,idle_long,delete_spike,rewrite_burst,cursor_back,code_edit,code_run,stall_empty_screen,stall_rewrite_cycle,stall_idle_pause',
            'metadata'    => 'nullable|array',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        return response()->json(
            $this->behavior->recordEvent(
                $userId,
                $data['course_id'],
                $data['subtopic_id'] ?? null,
                $data['event_type'],
                $data['metadata'] ?? [],
            )
        );
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'style'     => 'sometimes|string|in:practice_heavy,balanced,theory_heavy',
            'domain'    => 'nullable|string|max:64',
            'persona'   => 'sometimes|string|in:strict_lead,colleague,soft_mentor',
            'goal'      => 'nullable|string|in:senior_interview,mid_level_confidence,startup_architecture',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Не авторизован'], 401);
        }

        $profile = $this->profiles->updatePreferences($userId, $data['course_id'], [
            'style'   => $data['style'] ?? null,
            'domain'  => $data['domain'] ?? null,
            'persona' => $data['persona'] ?? null,
            'goal'    => $data['goal'] ?? null,
        ]);

        return response()->json(['profile' => $profile]);
    }

    private function resolveUserId(): ?int
    {
        if ($id = auth()->id()) {
            return $id;
        }

        if (app()->environment('local')) {
            return \App\Models\User::query()->value('id');
        }

        return null;
    }

    private function errorResponse(\Throwable $e): JsonResponse
    {
        $status = 500;

        if ($e instanceof RuntimeException) {
            $code = $e->getCode();
            if (is_int($code) && $code >= 400 && $code < 600) {
                $status = $code;
            }
        }

        $message = $e->getMessage();

        if ($e instanceof QueryException && str_contains($message, 'Unknown column')) {
            $message = 'Не применена миграция для уроков. Выполните: php artisan migrate';
        }

        if (app()->environment('local') && $status === 500) {
            $message .= ' [' . class_basename($e) . ']';
        }

        return response()->json(['error' => $message], $status);
    }
}
