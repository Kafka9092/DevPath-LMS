<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Models\UserLearningProfile;
use App\Models\UserProgressSubtopic;
use App\Service\CatAssessmentService;
use App\Service\TutorLearningService;
use App\Service\UserLearningProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/** Главная, создание курса, workspace, onboarding — весь user flow. */
class CourseController extends Controller
{
    public function index()
    {
        $userId = $this->resolveUserId();

        $seniorProgram = $this->resolveSeniorProgram();

        if (!$userId) {
            return Inertia::render('Main', [
                'courses'       => [],
                'seniorProgram' => $seniorProgram,
            ]);
        }

        $courses = User::find($userId)
            ->courses()
            ->with(['direction', 'level', 'modules.themes.subtopics'])
            ->get();

        return Inertia::render('Main', [
            'courses'       => $courses->map(fn ($c) => $this->formatCourseListItem($c, $userId))->values(),
            'seniorProgram' => $seniorProgram,
        ]);
    }

    private function resolveSeniorProgram(): ?array
    {
        if (! config('services.features.senior_program_card', true)) {
            return null;
        }

        $draft = session('course_plan_draft', []);

        if (strtolower((string) ($draft['real_level'] ?? '')) === 'senior') {
            return [
                'direction' => strtoupper((string) ($draft['direction'] ?? 'PHP')),
            ];
        }

        $userId = $this->resolveUserId();
        if (!$userId) {
            return null;
        }

        return app(CatAssessmentService::class)->resolveSeniorProgram($userId);
    }

    public function settings(Course $course, UserLearningProfileService $profiles)
    {
        $userId = $this->resolveUserId();
        if (!$userId) {
            return redirect()->route('main');
        }
        if (!$course->users()->where('user_id', $userId)->exists()) {
            abort(403);
        }

        $course->load(['direction', 'level']);
        $profile = $profiles->getProfile($userId, $course->id);

        return Inertia::render('CourseSettings', [
            'course' => [
                'id'        => $course->id,
                'title'     => $course->title,
                'direction' => $course->direction->name ?? '—',
                'level'     => $course->level->name ?? '—',
            ],
            'preferences' => $profile ? [
                'style'   => $profile->learning_preference,
                'domain'  => $profile->domain_interest,
                'persona' => $profile->mentor_persona,
                'goal'    => $profile->career_goal,
            ] : null,
        ]);
    }

    public function updateSettings(Request $request, Course $course, UserLearningProfileService $profiles)
    {
        $userId = $this->resolveUserId();
        if (!$userId) {
            return redirect()->route('main');
        }
        if (!$course->users()->where('user_id', $userId)->exists()) {
            abort(403);
        }

        $validated = $request->validate([
            'learning_preferences'          => 'required|array',
            'learning_preferences.style'    => 'required|string|in:practice_heavy,balanced,theory_heavy',
            'learning_preferences.domain'   => 'nullable|string|max:64',
            'learning_preferences.persona'  => 'required|string|in:strict_lead,colleague,soft_mentor',
            'learning_preferences.goal'     => 'nullable|string|in:senior_interview,mid_level_confidence,startup_architecture',
        ]);

        $profiles->updatePreferences($userId, $course->id, $validated['learning_preferences']);

        return redirect()->route('main')->with('success', 'Настройки курса сохранены');
    }

    public function destroy(Course $course)
    {
        $userId = $this->resolveUserId();
        if (!$userId) {
            return redirect()->route('main');
        }
        if (!$course->users()->where('user_id', $userId)->exists()) {
            abort(403);
        }

        UserProgressSubtopic::where('user_id', $userId)->where('course_id', $course->id)->delete();
        UserLearningProfile::where('user_id', $userId)->where('course_id', $course->id)->delete();
        $course->users()->detach($userId);

        return redirect()->route('main');
    }

    public function create()
    {
        return Inertia::render('CreateCourse');
    }

    public function planSettings(Request $request, CatAssessmentService $catAssessments)
    {
        $fromScratch = $request->boolean('from_scratch');

        if ($fromScratch) {
            $direction = strtoupper($request->query('direction', 'PHP'));
            $catAssessments->clearPlanSession();
            session([
                'course_plan_draft' => [
                    'direction'  => $direction,
                    'from_test'  => false,
                ],
            ]);

            return Inertia::render('PlanSettings', [
                'direction'      => $direction,
                'competences'    => [],
                'weak_topics'    => [],
                'strong_topics'  => [],
                'realLevel'      => null,
                'recommendation' => null,
                'from_test'      => false,
            ]);
        }

        $draft = session('course_plan_draft', []);

        if ($request->filled('direction')) {
            $draft['direction'] = strtoupper($request->query('direction'));
            session(['course_plan_draft' => $draft]);
        }

        $direction = strtoupper($draft['direction'] ?? 'PHP');
        $fromTest  = (bool) ($draft['from_test'] ?? false);

        if (!$fromTest) {
            $userId = $this->resolveUserId();
            if ($userId && app(CatAssessmentService::class)->ensureSeniorPlanDraft($userId, $direction)) {
                $draft = session('course_plan_draft', []);
                $fromTest = (bool) ($draft['from_test'] ?? false);
                $direction = strtoupper($draft['direction'] ?? $direction);
            }
        }

        if (!$fromTest) {
            return Inertia::render('PlanSettings', [
                'direction'      => $direction,
                'competences'    => [],
                'weak_topics'    => [],
                'strong_topics'  => [],
                'realLevel'      => null,
                'recommendation' => null,
                'from_test'      => false,
            ]);
        }

        $competences  = $draft['competences'] ?? [];
        $weakTopics   = $draft['weak_topics'] ?? array_keys(array_filter($competences, fn ($s) => $s < 50));
        $strongTopics = $draft['strong_topics'] ?? array_keys(array_filter($competences, fn ($s) => $s >= 80));

        return Inertia::render('PlanSettings', [
            'direction'       => $direction,
            'competences'     => $competences,
            'weak_topics'     => $weakTopics,
            'strong_topics'   => $strongTopics,
            'realLevel'       => $draft['real_level'] ?? null,
            'recommendation'  => $draft['recommendation'] ?? null,
            'from_test'       => true,
        ]);
    }

    public function storePlanSelection(Request $request)
    {
        $request->validate([
            'direction' => 'required|string|max:64',
            'level'     => 'required|string|in:beginner,junior,middle,senior',
        ]);

        $draft = session('course_plan_draft', []);

        session([
            'course_plan_draft' => array_merge($draft, [
                'direction'       => $request->input('direction'),
                'selected_level'  => $request->input('level'),
            ]),
        ]);

        return redirect()->route('onboarding');
    }

    public function onboarding()
    {
        $draft = session('course_plan_draft', []);

        if (empty($draft['direction']) || empty($draft['selected_level'])) {
            return redirect()->route('plan.settings', ['direction' => $draft['direction'] ?? 'PHP']);
        }

        return Inertia::render('Onboarding', [
            'direction'      => $draft['direction'],
            'level'          => $draft['selected_level'],
            'realLevel'      => $draft['real_level'] ?? null,
            'competences'    => $draft['competences'] ?? [],
            'weak_topics'    => $draft['weak_topics'] ?? [],
            'strong_topics'  => $draft['strong_topics'] ?? [],
            'recommendation' => $draft['recommendation'] ?? null,
        ]);
    }

    public function generatePlan(Request $request, TutorLearningService $tutorService)
    {
        $request->validate([
            'learning_preferences' => 'required|array',
            'learning_preferences.style' => 'required|string|in:practice_heavy,balanced,theory_heavy',
            'learning_preferences.domain' => 'nullable|string',
            'learning_preferences.persona' => 'required|string|in:strict_lead,colleague,soft_mentor',
            'learning_preferences.goal' => 'nullable|string|in:senior_interview,mid_level_confidence,startup_architecture',
        ]);

        $userId = $this->resolveUserId();
        if (!$userId) {
            return response()->json(['error' => 'Пользователь не авторизован'], 401);
        }

        $draft = session('course_plan_draft', []);
        $direction = $draft['direction'] ?? $request->input('direction');
        $level     = $draft['selected_level'] ?? $request->input('level');

        if (!$direction || !$level) {
            return response()->json(['error' => 'Сначала выберите язык и уровень курса'], 422);
        }

        try {
            $course = $tutorService->generateLearningPlan(
                directionName: $direction,
                levelName: $level,
                userId: $userId,
                competenceScores: $draft['competences'] ?? [],
                learningPreferences: $request->input('learning_preferences'),
            );

            session()->forget('course_plan_draft');

            return response()->json([
                'course_id'    => $course->id,
                'redirect_url' => route('workspace', $course->id),
            ]);
        } catch (\Exception $e) {
            Log::error('Plan generation failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function workspace(Course $course, CatAssessmentService $catAssessments)
    {
        $userId = $this->resolveUserId();
        if (!$userId) {
            return redirect()->route('main');
        }

        if (!$course->users()->where('user_id', $userId)->exists()) {
            return redirect()->route('main');
        }

        $course->load(['direction', 'level', 'modules.themes.subtopics']);

        $progress = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->get()
            ->keyBy('subtopic_id');

        $deliveryModes = $progress->pluck('delivery_mode', 'subtopic_id')->toArray();
        $completedIds  = $progress->where('is_completed', true)->pluck('subtopic_id')->toArray();

        $firstUnlocked = null;
        foreach ($course->modules as $module) {
            foreach ($module->themes as $theme) {
                foreach ($theme->subtopics->sortBy('order') as $subtopic) {
                    if (!in_array($subtopic->id, $completedIds, true)) {
                        $firstUnlocked = [
                            'id'       => $subtopic->id,
                            'title'    => $subtopic->title,
                            'theme_id' => $theme->id,
                        ];
                        break 3;
                    }
                }
            }
        }

        $lessonService = app(\App\Service\LessonService::class);

        $exerciseHistory = UserProgressSubtopic::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->where('is_completed', true)
            ->with('subtopic.theme')
            ->orderByDesc('completed_at')
            ->get()
            ->map(fn ($p) => $lessonService->formatHistoryItem($p));

        $profile = UserLearningProfile::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->first();

        $initialCat = $catAssessments->getBaselineForCourse($userId, $course->id);

        return Inertia::render('Workspace', [
            'course'              => $course,
            'delivery_modes'      => $deliveryModes,
            'completed_ids'       => $completedIds,
            'first_unlocked'      => $firstUnlocked,
            'exercise_history'    => $exerciseHistory,
            'user_profile'        => $profile,
            'initial_cat'         => $initialCat,
            'unlock_all_lessons'  => false,
        ]);
    }

    private function formatCourseListItem(Course $course, int $userId): array
    {
        $totalSubtopics = 0;
        foreach ($course->modules as $module) {
            foreach ($module->themes as $theme) {
                $totalSubtopics += $theme->subtopics->count();
            }
        }

        $completed = UserProgressSubtopic::query()
            ->where('user_id', $userId)
            ->where('course_id', $course->id)
            ->where('is_completed', true)
            ->count();

        $progress = $totalSubtopics > 0
            ? (int) min(100, round(100 * $completed / $totalSubtopics))
            : (int) ($course->pivot->progress ?? 0);

        if ($course->pivot && (int) $course->pivot->progress !== $progress) {
            $course->users()->updateExistingPivot($userId, ['progress' => $progress]);
        }

        return [
            'id'        => $course->id,
            'title'     => $course->title,
            'direction' => $course->direction->name ?? '—',
            'level'     => $course->level->name ?? '—',
            'progress'  => $progress,
            'status'    => $course->pivot->status ?? 'active',
        ];
    }

    private function resolveUserId(): ?int
    {
        if ($id = Auth::id()) {
            return $id;
        }

        if (app()->environment('local')) {
            return User::query()->value('id');
        }

        return null;
    }
}
