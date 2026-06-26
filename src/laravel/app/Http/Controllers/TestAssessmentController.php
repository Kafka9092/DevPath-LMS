<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Service\CatAssessmentService;
use App\Service\TestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

// Входной адаптивный тест (CAT)
class TestAssessmentController extends Controller
{
    protected TestService $testService;

    public function __construct(
        TestService $testService,
        protected CatAssessmentService $catAssessments,
    ) {
        $this->testService = $testService;
    }

    public function prepareRetake(): \Illuminate\Http\JsonResponse
    {
        $this->catAssessments->clearPlanSession();

        return response()->json(['ok' => true]);
    }

    public function show(Request $request)
    {
        $direction = $request->query('direction');

        if (!$direction) {
            return redirect()->route('create.course'); 
        }

        return Inertia::render('TestPage', [
            'direction' => $direction,
            'session'   => $request->query('session'),
            'fresh'     => $request->boolean('fresh'),
        ]);
    }

    public function startAdaptiveTest(Request $request)
    {
        $request->validate([
            'direction'  => 'required|string',
            'session_id' => 'nullable|uuid',
        ]);

        $sessionId = $request->input('session_id') ?: (string) Str::uuid();

        return response()->json(
            $this->testService->startAdaptiveTest($request->input('direction'), $sessionId)
        );
    }

    public function sessionState(string $sessionId)
    {
        return response()->json($this->testService->getSessionState($sessionId));
    }

    public function advance(Request $request)
    {
        $request->validate(['session_id' => 'required|string']);

        return response()->json(
            $this->testService->advanceSession($request->input('session_id'))
        );
    }

    public function submitAnswer(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'question_id' => 'required|integer',
            'answer' => 'required',
            
        ]);

        return response()->json(
            $this->testService->submitAdaptiveAnswer(
                $request->input('session_id'),
                $request->input('question_id'),
                $request->input('answer'),
                $this->resolveUserId(),
            )
        );
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

    public function forceSeniorPreview(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! app()->environment('local')) {
            return response()->json(['error' => true, 'message' => 'Недоступно'], 403);
        }

        $direction = strtoupper($request->input('direction', 'PHP'));

        $competences = [
            'Условия (if/else)'       => 100,
            'Функции (базовые)'       => 100,
            'Циклы (for/while)'       => 100,
            'ООП и классы'            => 100,
            'Массивы и коллекции'     => 100,
            'Работа с БД'             => 100,
            'Архитектура и паттерны'  => 100,
        ];

        $result = [
            'overall_level'   => 'senior',
            'description'     => 'Senior-разработчик. Глубокое понимание и богатый опыт.',
            'recommendation'  => 'Доступна программа Senior: стандартное обучение и собеседование.',
            'competencies'    => $competences,
            'weak_topics'     => [],
            'strong_topics'   => array_keys($competences),
            'code_feedback'   => '',
        ];

        session([
            'course_plan_draft' => [
                'direction'      => $direction,
                'real_level'     => 'senior',
                'from_test'      => true,
                'competences'    => $competences,
                'weak_topics'    => [],
                'strong_topics'  => array_keys($competences),
                'recommendation' => $result['recommendation'],
            ],
        ]);

        return response()->json([
            'ok'        => true,
            'direction' => $direction,
            'result'    => $result,
        ]);
    }

    public function completeTask(Request $request)
    {
        $request->validate([
            'assessment_uuid' => 'required|uuid',
            'code'            => 'nullable|string',
            'skipped'         => 'sometimes|boolean',
        ]);

        return response()->json(
            $this->testService->submitPracticalTask(
                $request->input('assessment_uuid'),
                $request->input('code'),
                $request->boolean('skipped', false),
            )
        );
    }
}
