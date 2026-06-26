<?php

namespace App\Service;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Service\OllamaService;
use App\Service\Kafka\CatTestKafkaService;
use App\Models\Competence;

// Адаптивный CAT-тест при создании курса.Сессия живёт в Cache, результат — в cat_assessments.

class TestService
{
    protected OllamaService $ollama;
    protected CatAssessmentService $catAssessments;
    protected CatTestKafkaService $kafka;
    protected LanguageCompetencePromptBuilder $languagePrompts;
//максимум вопросов 25
    
    private const SESSION_TTL_SECONDS    = 3600;

    private const MAX_QUESTIONS          = 25;
    private const LIVES_PER_LEVEL        = 2;
    private const STREAK_FOR_EARLY_STOP  = 5;  
    private const CORRECT_TO_CONFIRM     = 2;   
    private const FAILS_TO_CONFIRM_FLOOR = 3;   

    public function __construct(
        OllamaService $ollama,
        CatAssessmentService $catAssessments,
        CatTestKafkaService $kafka,
        LanguageCompetencePromptBuilder $languagePrompts,
    ) {
        $this->ollama = $ollama;
        $this->catAssessments = $catAssessments;
        $this->kafka = $kafka;
        $this->languagePrompts = $languagePrompts;
    }


    // Старт теста
    public function startAdaptiveTest(string $direction, string $sessionId): array
    {
        if ($this->kafka->useKafkaFor('start')) {
            Cache::put($this->sessionKey($sessionId), [
                'direction'           => $direction,
                'generation_status'   => 'generating_start',
                'questions'           => [],
                'answers'             => [],
            ], self::SESSION_TTL_SECONDS);

            $jobId = $this->kafka->publish(
                $this->kafka->topic('start'),
                ['session_id' => $sessionId, 'direction' => $direction],
                $sessionId,
            );

            return [
                'session_id'  => $sessionId,
                'status'      => 'generating_start',
                'job_id'      => $jobId,
                'is_finished' => false,
                'direction'   => $direction,
            ];
        }

        return $this->startAdaptiveTestSync($direction, $sessionId);
    }

    /** Фронт опрашивает это пока generation_status != ready. */
    public function getSessionState(string $sessionId): array
    {
        $finished = Cache::get($this->finishedKey($sessionId));
        if ($finished) {
            $gen = $finished['generation_status'] ?? null;
            if ($gen === 'generating_task' && empty($finished['practical_task']['title'] ?? null)) {
                return $this->formatFinishedPayload($finished, $sessionId, 'generating_task');
            }

            return $this->formatFinishedPayload($finished, $sessionId, 'finished');
        }

        $session = Cache::get($this->sessionKey($sessionId));
        if (!$session) {
            return ['status' => 'missing', 'error' => true, 'message' => 'Сессия не найдена'];
        }

        $gen = $session['generation_status'] ?? null;

        if ($gen === 'generating_start') {
            if (!empty($session['questions'])) {
                unset($session['generation_status']);
                Cache::put($this->sessionKey($sessionId), $session, self::SESSION_TTL_SECONDS);

                return $this->formatActiveSession($session, $sessionId, 'ready');
            }

            return [
                'status'     => 'generating_start',
                'session_id' => $sessionId,
                'direction'  => $session['direction'] ?? '',
            ];
        }

        if ($gen === 'generating_next') {
            $answered = count($session['answers'] ?? []);
            if (count($session['questions'] ?? []) > $answered) {
                unset($session['generation_status']);
                Cache::put($this->sessionKey($sessionId), $session, self::SESSION_TTL_SECONDS);

                return $this->formatActiveSession($session, $sessionId, 'ready');
            }

            return $this->formatActiveSession($session, $sessionId, 'generating_next');
        }

        return $this->formatActiveSession($session, $sessionId, 'ready');
    }

    public function advanceSession(string $sessionId): array
    {
        $session = Cache::get($this->sessionKey($sessionId));
        if (!$session) {
            return ['error' => true, 'message' => 'Сессия не найдена'];
        }

        $answered = count($session['answers'] ?? []);

        if (($session['generation_status'] ?? null) === 'generating_next') {
            if (count($session['questions'] ?? []) <= $answered) {
                $generated = $this->generateNextQuestionFromKafka($sessionId);
                if (! empty($generated['error'])) {
                    return [
                        'error'   => true,
                        'message' => $generated['message'] ?? 'Не удалось сгенерировать следующий вопрос',
                    ];
                }

                $session = Cache::get($this->sessionKey($sessionId));
                if (! $session) {
                    return ['error' => true, 'message' => 'Сессия не найдена'];
                }
            } else {
                unset($session['generation_status']);
            }
        }

        $session['ui'] = array_merge($session['ui'] ?? [], ['awaiting_continue' => false]);
        Cache::put($this->sessionKey($sessionId), $session, self::SESSION_TTL_SECONDS);

        $idx      = count($session['answers'] ?? []);
        $question = $session['questions'][$idx] ?? null;

        if (! $question) {
            return ['error' => true, 'message' => 'Следующий вопрос ещё не готов'];
        }

        return [
            'status'          => 'ready',
            'session_id'      => $sessionId,
            'question'        => $question,
            'question_number' => $idx + 1,
            'display_level'   => $session['display_level'] ?? 'determining',
            'current_streak'  => $session['current_streak'] ?? 0,
            'total_questions' => self::MAX_QUESTIONS,
            'is_finished'     => false,
            'generating_next' => false,
        ];
    }

    public function startAdaptiveTestSync(string $direction, string $sessionId): array
    {
        if (!$this->ollama->isAvailable()) {
            return ['error' => true, 'message' => 'AI недоступен'];
        }

        $startLevel = 'junior';
        $comps      = $this->getCompetencesForLevel($startLevel);

        if ($comps === [] && app()->environment('local')) {
            try {
                Artisan::call('db:seed', ['--class' => 'CompetenceSeeder', '--force' => true]);
                $comps = $this->getCompetencesForLevel($startLevel);
            } catch (\Throwable $e) {
                Log::warning('CompetenceSeeder auto-run failed: ' . $e->getMessage());
            }
        }

        if ($comps === []) {
            return [
                'error'   => true,
                'message' => 'В базе нет компетенций для уровня «' . $startLevel . '». Выполните: php artisan db:seed --class=CompetenceSeeder',
            ];
        }

        $firstComp = $comps[array_rand($comps)];

        try {
            $qData = $this->ollama->generateJson(
                $this->buildQuestionPrompt([], $direction, $firstComp['name'], $firstComp['category'], $startLevel, 'single')
            );
        } catch (\Exception $e) {
            return ['error' => true, 'message' => 'Ошибка генерации: ' . $e->getMessage()];
        }

        $firstQuestion = $this->makeQuestion(1, $qData, $startLevel, $firstComp['id']);

        $session = [
            'direction'                    => $direction,
            'questions'                    => [$firstQuestion],
            'answers'                      => [],
            'asked_by_level'               => ['beginner' => [], 'junior' => [], 'middle' => [], 'senior' => []],
            'asked_texts'                  => [$qData['text']],
            'current_question_level'       => $startLevel,
            'display_level'                => 'determining',
            'lives'                        => [
                'beginner' => self::LIVES_PER_LEVEL,
                'junior'   => self::LIVES_PER_LEVEL,
                'middle'   => self::LIVES_PER_LEVEL,
                'senior'   => self::LIVES_PER_LEVEL,
            ],
            'ceiling'                      => 'senior',
            'current_streak'               => 0,
            'confirmed_level'              => null,
            'consecutive_correct_on_level' => 0,
            'beginner_fails'               => 0,
            'matrix'                       => [],
        ];

        $session['asked_by_level'][$startLevel][] = $firstComp['id'];

        Cache::put($this->sessionKey($sessionId), $session, self::SESSION_TTL_SECONDS);

        return [
            'session_id'      => $sessionId,
            'status'          => 'ready',
            'question'        => $firstQuestion,
            'question_number' => 1,
            'total_questions' => self::MAX_QUESTIONS,
            'display_level'   => 'determining',
            'is_finished'     => false,
        ];
    }

    
    public function generateNextQuestionFromKafka(string $sessionId): array
    {
        $session = Cache::get($this->sessionKey($sessionId));
        if (!$session) {
            return ['error' => true, 'message' => 'Сессия не найдена'];
        }

        if (!$this->ollama->isAvailable()) {
            return ['error' => true, 'message' => 'AI недоступен'];
        }

        try {
            return $this->buildNextQuestionResponse($session, $sessionId);
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    
    public function generatePracticalTaskFromKafka(string $sessionId, string $direction, string $finalLevel): array
    {
        if (!$this->ollama->isAvailable()) {
            return ['error' => true, 'message' => 'AI недоступен'];
        }

        try {
            $taskData = $this->ollama->generateJson($this->buildTaskPrompt($direction, $finalLevel));
            $task     = $this->sanitizePracticalTaskForClient($taskData['task'] ?? []);

            $finished = Cache::get($this->finishedKey($sessionId));
            if ($finished) {
                $finished['practical_task'] = $task;
                unset($finished['generation_status']);
                Cache::put($this->finishedKey($sessionId), $finished, self::SESSION_TTL_SECONDS);
            }

            return ['practical_task' => $task];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => 'Ошибка генерации задачи: ' . $e->getMessage()];
        }
    }

    // После ответа — поднимаем/опускаем уровень
    public function submitAdaptiveAnswer(string $sessionId, int $questionId, $answer, ?int $userId = null): array
    {
        $session = Cache::get($this->sessionKey($sessionId));
        if (!$session) return ['error' => true, 'message' => 'Сессия не найдена'];

        $currentQ = collect($session['questions'])->firstWhere('id', $questionId);
        if (!$currentQ) return ['error' => true, 'message' => 'Вопрос не найден'];

      
        $isCorrect = $this->evaluateAnswer($currentQ, $answer);

        $levelOrder     = ['beginner', 'junior', 'middle', 'senior'];
        $currentLvlName = $session['current_question_level'];
        $currentLvlIdx  = array_search($currentLvlName, $levelOrder);

        $this->updateMatrix($session, $currentQ['competence_id'], $isCorrect, $currentLvlName);

        $session['answers'][] = [
            'competence_id'     => $currentQ['competence_id'],
            'level'             => $currentLvlName,
            'is_correct'        => $isCorrect,
            'submitted_answer'  => $answer,
            'question_id'       => $questionId,
        ];

        if ($isCorrect) {
            $session['current_streak']++;
            $session['consecutive_correct_on_level']++;

            if ($session['consecutive_correct_on_level'] >= self::CORRECT_TO_CONFIRM) {
                $session['confirmed_level'] = $currentLvlName;
                $session['display_level']   = $currentLvlName;
            }

            $ceilingIdx = array_search($session['ceiling'], $levelOrder);
            if ($currentLvlIdx < $ceilingIdx) {
                $nextLevel = $levelOrder[$currentLvlIdx + 1];
                $session['consecutive_correct_on_level'] = 0; 
                $session['current_question_level']       = $nextLevel;
            }

        } else {
            $session['current_streak']             = 0;
            $session['consecutive_correct_on_level'] = 0;

            if ($currentLvlName === 'beginner') {
                $session['beginner_fails']++;
            }

            $session['lives'][$currentLvlName]--;

            if ($session['lives'][$currentLvlName] <= 0) {
                $newCeilingIdx             = max(0, $currentLvlIdx - 1);
                $session['ceiling']        = $levelOrder[$newCeilingIdx];
                $session['current_question_level'] = $session['ceiling'];

                if ($session['display_level'] !== 'determining') {
                    $confirmedIdx = array_search($session['display_level'], $levelOrder);
                    if ($confirmedIdx > $newCeilingIdx) {
                        $session['display_level']   = $session['ceiling'];
                        $session['confirmed_level'] = $session['ceiling'];
                    }
                }
            } else {
                if ($currentLvlIdx > 0) {
                    $session['current_question_level'] = $levelOrder[$currentLvlIdx - 1];
                }
            }

            if ($session['display_level'] === 'determining' && $currentLvlName === 'beginner') {
                $session['display_level'] = 'beginner';
            }
        }

        $answeredCount = count($session['answers']);
        $stopReason    = null;

        if ($answeredCount >= self::MAX_QUESTIONS) {
            $stopReason = 'limit';
        }

        if (!$stopReason && $session['current_streak'] >= self::STREAK_FOR_EARLY_STOP) {
            $lastAnswers = array_slice($session['answers'], -self::STREAK_FOR_EARLY_STOP);
            $lastLevels  = array_column($lastAnswers, 'level');
            $allCorrect  = array_column($lastAnswers, 'is_correct');
            if (
                count(array_unique($lastLevels)) === 1
                && !in_array(false, $allCorrect)
                && $lastLevels[0] === $session['ceiling']
            ) {
                $stopReason = 'solid_level';
            }
        }

        if (!$stopReason) {
            $begginerLifesGone = $session['ceiling'] === 'beginner'
                && $session['lives']['beginner'] <= 0;
            $tooManyBeginnerFails = $session['beginner_fails'] >= self::FAILS_TO_CONFIRM_FLOOR;

            if ($begginerLifesGone || $tooManyBeginnerFails) {
                $stopReason = 'confirmed_beginner';
            }
        }
        if (!$stopReason && $session['ceiling'] === 'beginner' && $session['lives']['beginner'] <= 0) {
            $stopReason = 'floor_reached';
        }

        if ($stopReason) {
            return $this->finishTest($session, $sessionId, $isCorrect, $stopReason, $userId);
        }

        Cache::put($this->sessionKey($sessionId), $session, self::SESSION_TTL_SECONDS);

        if ($this->kafka->useKafkaFor('next')) {
            $session['generation_status'] = 'generating_next';
            $session['ui']                = [
                'awaiting_continue' => true,
                'last_is_correct'   => $isCorrect,
            ];
            Cache::put($this->sessionKey($sessionId), $session, self::SESSION_TTL_SECONDS);

            try {
                $this->kafka->publish(
                    $this->kafka->topic('next_question'),
                    ['session_id' => $sessionId],
                    $sessionId,
                );
            } catch (\Throwable $e) {
                return ['error' => true, 'message' => 'Kafka publish error: ' . $e->getMessage()];
            }

            return [
                'is_finished'     => false,
                'is_correct'      => $isCorrect,
                'display_level'   => $session['display_level'],
                'current_streak'  => $session['current_streak'],
                'status'          => 'generating_next',
                'generating_next' => true,
            ];
        }

        return $this->buildNextQuestionResponse($session, $sessionId, $isCorrect);
    }

    private function buildNextQuestionResponse(array $session, string $sessionId, ?bool $isCorrect = null): array
    {
        $answeredCount = count($session['answers']);
        $nextComp      = $this->getNextCompetence($session);
        $nextLevel     = $session['current_question_level'];

        $nextQuestionNumber = $answeredCount + 1;
        $requiredType       = ($nextQuestionNumber % 2 === 0) ? 'multiple' : 'single';

        $nextQData = $this->ollama->generateJson($this->buildQuestionPrompt(
            $session['asked_texts'],
            $session['direction'],
            $nextComp['name'],
            $nextComp['category'],
            $nextLevel,
            $requiredType,
        ));

        $nextQuestion = $this->makeQuestion($nextQuestionNumber, $nextQData, $nextLevel, $nextComp['id'], $requiredType);

        $session['questions'][]                  = $nextQuestion;
        $session['asked_texts'][]                = $nextQData['text'];
        $session['asked_by_level'][$nextLevel][] = $nextComp['id'];
        unset($session['generation_status']);

        Cache::put($this->sessionKey($sessionId), $session, self::SESSION_TTL_SECONDS);

        $response = [
            'is_finished'     => false,
            'question'        => $nextQuestion,
            'question_number' => $answeredCount + 1,
            'display_level'   => $session['display_level'],
            'current_streak'  => $session['current_streak'],
        ];

        if ($isCorrect !== null) {
            $response['is_correct'] = $isCorrect;
        }

        return $response;
    }

    private function finishTest(
        array $session,
        string $sessionId,
        bool $isCorrect,
        string $stopReason,
        ?int $userId = null,
    ): array {
        $finalLevel = $session['confirmed_level']
            ?? ($session['display_level'] !== 'determining' ? $session['display_level'] : 'beginner');

        $result = $this->prepareTestResult($session, $finalLevel);

        $assessmentUuid = null;
        if ($userId) {
            try {
                $assessmentUuid = $this->catAssessments->persistFromCatSession(
                    $userId,
                    $session,
                    $result,
                    $stopReason,
                );
            } catch (\Throwable $e) {
                Log::error('Failed to persist CAT assessment: ' . $e->getMessage());
            }
        }

        $this->storeAssessmentInSession($session, $result, $assessmentUuid);

        $answeredCount = count($session['answers'] ?? []);
        $lastQuestion  = $answeredCount > 0
            ? ($session['questions'][$answeredCount - 1] ?? null)
            : null;
        $lastAnswer    = $answeredCount > 0
            ? ($session['answers'][$answeredCount - 1] ?? null)
            : null;

        $finishedPayload = [
            'result'            => $result,
            'is_correct'        => $isCorrect,
            'stop_reason'       => $stopReason,
            'assessment_uuid'   => $assessmentUuid,
            'assessment_saved'  => $assessmentUuid !== null,
            'direction'         => $session['direction'],
            'final_level'       => $finalLevel,
            'practical_task'    => [],
            'generation_status' => null,
            'resume'            => [
                'question'              => $lastQuestion,
                'question_number'       => max(1, $answeredCount),
                'show_feedback'         => true,
                'last_is_correct'       => $isCorrect,
                'last_submitted_answer' => $lastAnswer['submitted_answer'] ?? null,
            ],
        ];

        if ($this->kafka->useKafkaFor('finish')) {
            $finishedPayload['generation_status'] = 'generating_task';
            Cache::put($this->finishedKey($sessionId), $finishedPayload, self::SESSION_TTL_SECONDS);
            Cache::forget($this->sessionKey($sessionId));

            try {
                $this->kafka->publish(
                    $this->kafka->topic('finish'),
                    [
                        'session_id'  => $sessionId,
                        'direction'   => $session['direction'],
                        'final_level' => $finalLevel,
                    ],
                    $sessionId,
                );
            } catch (\Throwable $e) {
                Log::warning('Kafka task publish failed: ' . $e->getMessage());
            }

            return [
                'is_finished'     => true,
                'result'          => $result,
                'is_correct'      => $isCorrect,
                'stop_reason'     => $stopReason,
                'practical_task'  => [],
                'assessment_saved'=> $assessmentUuid !== null,
                'assessment_uuid' => $assessmentUuid,
                'status'          => 'generating_task',
                'generating_task' => true,
            ];
        }

        $generated = $this->ollama->generateJson($this->buildTaskPrompt($session['direction'], $finalLevel));
        $finishedPayload['practical_task'] = $this->sanitizePracticalTaskForClient($generated['task'] ?? []);
        Cache::put($this->finishedKey($sessionId), $finishedPayload, self::SESSION_TTL_SECONDS);
        Cache::forget($this->sessionKey($sessionId));

        return [
            'is_finished'       => true,
            'result'            => $result,
            'is_correct'        => $isCorrect,
            'stop_reason'       => $stopReason,
            'practical_task'    => $finishedPayload['practical_task'],
            'assessment_saved'  => $assessmentUuid !== null,
            'assessment_uuid'   => $assessmentUuid,
        ];
    }

    public function submitPracticalTask(?string $assessmentUuid, ?string $code, bool $skipped = false): array
    {
        if (!$assessmentUuid) {
            return [
                'is_complete'   => true,
                'code_feedback' => null,
                'skipped'       => true,
                'error'         => 'assessment_uuid не передан',
            ];
        }

        try {
            return $this->catAssessments->recordPracticalTask($assessmentUuid, $code, $skipped);
        } catch (\Throwable $e) {
            Log::error('Practical task persist error: ' . $e->getMessage());

            return [
                'is_complete'   => true,
                'code_feedback' => null,
                'skipped'       => $skipped,
                'error'         => $e->getMessage(),
            ];
        }
    }

    private function makeQuestion(int $id, array $qData, string $level, int $competenceId, ?string $requiredType = null): array
    {
        return [
            'id'            => $id,
            'type'          => $this->normalizeQuestionType($qData, $requiredType),
            'topic'         => $qData['topic'] ?? '',
            'difficulty'    => $level,
            'text'          => $qData['text'] ?? '',
            'options'       => $qData['options'] ?? [],
            'correct'       => $qData['correct'] ?? [0],
            'competence_id' => $competenceId,
        ];
    }

    private function normalizeQuestionType(array $qData, ?string $requiredType = null): string
    {
        $type = strtolower((string) ($qData['type'] ?? 'single'));

        if ($type === 'multiple') {
            return 'multiple';
        }

        $correct = $qData['correct'] ?? [];
        if (is_array($correct) && count($correct) > 1) {
            return 'multiple';
        }

        $options = $qData['options'] ?? [];
        if (is_array($options) && count($options) >= 5) {
            return 'multiple';
        }

        $text = (string) ($qData['text'] ?? '');
        if (stripos($text, 'выберите все') !== false) {
            return 'multiple';
        }

        if ($requiredType === 'multiple') {
            return 'multiple';
        }

        return 'single';
    }

    private function getCompetencesForLevel(string $level): array
    {
        $col = match ($level) {
            'beginner' => 'beginner_level',
            'junior'   => 'junior_level',
            'middle'   => 'middle_level',
            'senior'   => 'senior_level',
            default    => 'junior_level',
        };

        return Competence::query()
            ->where($col, true)
            ->select('id', 'name', 'category')
            ->get()
            ->toArray();
    }

    private function updateMatrix(array &$session, int $compId, bool $isCorrect, string $level): void
    {
        if (!isset($session['matrix'][$level][$compId])) {
            $session['matrix'][$level][$compId] = $isCorrect;
        } else {
            $session['matrix'][$level][$compId] = $session['matrix'][$level][$compId] && $isCorrect;
        }
    }

    private function getNextCompetence(array &$session): array
    {
        $level        = $session['current_question_level'];
        $askedOnLevel = $session['asked_by_level'][$level] ?? [];
        $comps        = $this->getCompetencesForLevel($level);
        $available = array_values(array_filter($comps, fn ($c) => !in_array($c['id'], $askedOnLevel)));
        $pool      = $available !== [] ? $available : $comps;

        if ($pool === []) {
            throw new \RuntimeException(
                'Нет компетенций для уровня «' . $level . '». Запустите: php artisan db:seed --class=CompetenceSeeder'
            );
        }

        return $pool[array_rand($pool)];
    }

    private function prepareTestResult(array $session, string $finalLevel): array
    {
        $competencies = [];
        $weak         = [];
        $strong       = [];

        foreach ($session['matrix'] as $lvl => $data) {
            foreach ($data as $cid => $status) {
                $name                  = Competence::find($cid)?->name ?? "Topic $cid";
                $competencies[$name]   = $status ? 100 : 0;
                $status ? ($strong[] = $name) : ($weak[] = $name);
            }
        }

        $levelDescriptions = [
            'beginner' => 'Начинающий разработчик. Базовые концепции ещё осваиваются.',
            'junior'   => 'Junior-разработчик. Знаете основы, нужно больше практики.',
            'middle'   => 'Middle-разработчик. Уверенно работаете с языком и инструментами.',
            'senior'   => 'Senior-разработчик. Глубокое понимание и богатый опыт.',
        ];

        return [
            'overall_level'   => $finalLevel,
            'description'     => $levelDescriptions[$finalLevel] ?? '',
            'competencies'    => $competencies,
            'weak_topics'     => array_values(array_unique($weak)),
            'strong_topics'   => array_values(array_unique($strong)),
            'recommendation'  => $this->buildRecommendation($finalLevel, array_values(array_unique($weak))),
            'code_feedback'   => '',
        ];
    }

    private function buildRecommendation(string $level, array $weakTopics): string
    {
        $base = match($level) {
            'beginner' => 'Рекомендуем начать с фундаментальных концепций языка.',
            'junior'   => 'Сосредоточьтесь на практических проектах и алгоритмах.',
            'middle'   => 'Изучите архитектурные паттерны и оптимизацию кода.',
            'senior'   => 'Развивайте лидерские навыки и системное проектирование.',
        };

        if (!empty($weakTopics)) {
            $topics = implode(', ', array_slice($weakTopics, 0, 3));
            $base  .= " Обратите особое внимание на: $topics.";
        }

        return $base;
    }

    private function buildQuestionPrompt(
        array  $askedTexts,
        string $dir,
        string $comp,
        string $cat,
        string $lvl,
        ?string $requiredType = null,
    ): string {
        $forbidden = !empty($askedTexts)
            ? "НЕ ПОВТОРЯЙ эти вопросы:\n- " . implode("\n- ", array_slice($askedTexts, -10))
            : '';

        $typeInstruction = match ($requiredType) {
            'multiple' => "ТИП: multiple (обязательно). "
                . "От 5 до 7 вариантов ответа, 2–4 правильных (чекбоксы). "
                . "В тексте вопроса обязательно фраза «(выберите все подходящие)».",
            'single'   => "ТИП: single (обязательно). Ровно 4 варианта, ровно 1 правильный (радиокнопки).",
            default    => "ТИП: чередуй 'single' и 'multiple' (примерно каждый 2-й вопрос — multiple). "
                . "single: ровно 4 варианта, 1 правильный. "
                . "multiple: от 5 до 7 вариантов (не меньше 5), 2–4 правильных ответа (чекбоксы).",
        };

        $levelHints = [
            'beginner' => 'базовый синтаксис, типы данных, простые конструкции',
            'junior'   => 'функции, ООП-основы, стандартные библиотеки',
            'middle'   => 'архитектура, паттерны, производительность, тестирование',
            'senior'   => 'системный дизайн, оптимизация, продвинутые паттерны, безопасность',
        ];

        $langLabel = $this->languagePrompts->normalizeDirectionLabel($dir);
        $languageRules = $this->languagePrompts->buildRules($dir, $comp, $cat, 'test');

        return <<<PROMPT
Ты — генератор вопросов для технического интервью.
Язык программирования теста: {$langLabel}
Компетенция: {$comp} (категория: {$cat})
Уровень сложности: {$lvl} ({$levelHints[$lvl]})
{$typeInstruction}

{$languageRules}

{$forbidden}

Верни ТОЛЬКО валидный JSON без пояснений, markdown и кавычек снаружи:
{
  "type": "single" или "multiple",
  "topic": "{$comp}",
  "difficulty": "{$lvl}",
  "text": "Текст вопроса",
  "options": ["вариант 1", "вариант 2", ...],
  "correct": [индексы правильных, например [0] для single или [1, 3, 5] для multiple]
}

Для single: массив options длиной 4. Для multiple: длина options от 5 до 7, в correct минимум 2 индекса.
В тексте multiple обязательно фраза «(выберите все подходящие)».
PROMPT;
    }

    private function buildEvaluationPrompt(array $q, $ans): string
    {
        $correctIndices = json_encode($q['correct']);
        $userAnswer     = is_array($ans) ? json_encode($ans) : json_encode([(int)$ans]);
        $optionsFormatted = $this->formatOptions($q['options']);

        return <<<PROMPT
Оцени ответ на вопрос теста по программированию.

Вопрос: {$q['text']}
Варианты (индекс: текст): {$optionsFormatted}
Правильные индексы: {$correctIndices}
Ответ пользователя (индексы): {$userAnswer}

Для multiple-choice: ответ верен только если пользователь выбрал ВСЕ правильные и НИ ОДНОГО лишнего.

Верни ТОЛЬКО JSON: {"is_correct": true} или {"is_correct": false}
PROMPT;
    }

    private function buildTaskPrompt(string $dir, string $lvl): string
    {
        $complexity = match($lvl) {
            'beginner' => 'очень простая, 5-10 строк кода, базовые конструкции',
            'junior'   => 'простая, 10-20 строк, функции и базовые алгоритмы',
            'middle'   => 'средняя, требует понимания ООП или алгоритмов',
            'senior'   => 'сложная, системное мышление, оптимизация или архитектура',
        };

        return <<<PROMPT
Создай практическую задачу по программированию.
Язык: {$dir}
Уровень: {$lvl} — {$complexity}

Задача должна быть конкретной и проверяемой ментором по коду:
1. title — чёткое название
2. description — контекст 1–2 предложения (без примеров «вход/выход»)
3. action — «Напишите функцию …»
4. function_signature — с типами аргументов и return
5. constraints — ≥2 правила: типы данных и edge cases
6. starter_code — каркас с TODO

Не включай test_cases, example_input, example_output — студент отправляет код на проверку ментору.

Верни ТОЛЬКО JSON:
{
  "task": {
    "title": "Название задачи",
    "description": "Контекст",
    "action": "Чёткое действие",
    "function_signature": "function name(type \$arg): type",
    "constraints": ["типы и edge case 1", "правило 2"],
    "starter_code": "// каркас с TODO"
  }
}
PROMPT;
    }

    private function buildCodeFeedbackPrompt(string $code): string
    {
        return <<<PROMPT
Проанализируй код и дай конструктивный фидбек.

Код:
```
{$code}
```

Верни ТОЛЬКО JSON:
{
  "summary": "Краткая оценка в 1-2 предложениях",
  "strengths": ["что сделано хорошо 1", "что сделано хорошо 2"],
  "improvements": ["что улучшить 1", "что улучшить 2"],
  "score": число от 0 до 100
}
PROMPT;
    }

    private function formatOptions(array $options): string
    {
        return implode(', ', array_map(fn($opt, $i) => "$i: $opt", $options, array_keys($options)));
    }

    private function evaluateAnswer(array $question, mixed $answer): bool
    {
        if ($answer === -1 || $answer === '-1' || $answer === [-1]) {
            return false;
        }

        $userIndices = is_array($answer)
            ? array_map('intval', $answer)
            : [(int) $answer];
        sort($userIndices);

        $correct = array_map('intval', $question['correct'] ?? [0]);
        sort($correct);

        return $userIndices === $correct;
    }

    private function storeAssessmentInSession(array $session, array $result, ?string $assessmentUuid = null): void
    {
        session([
            'course_plan_draft' => [
                'direction'      => strtoupper($session['direction'] ?? ''),
                'competences'    => $result['competencies'] ?? [],
                'weak_topics'    => $result['weak_topics'] ?? [],
                'strong_topics'  => $result['strong_topics'] ?? [],
                'real_level'     => $result['overall_level'] ?? 'junior',
                'recommendation' => $result['recommendation'] ?? '',
                'from_test'      => true,
            ],
            'cat_assessment_uuid' => $assessmentUuid,
        ]);
    }

    private function sessionKey(string $sessionId): string
    {
        return 'test_session_' . $sessionId;
    }

    private function finishedKey(string $sessionId): string
    {
        return 'test_finished_' . $sessionId;
    }

    private function formatActiveSession(array $session, string $sessionId, string $status): array
    {
        $answers   = $session['answers'] ?? [];
        $questions = $session['questions'] ?? [];
        $awaiting  = (bool) ($session['ui']['awaiting_continue'] ?? false);
        $answered  = count($answers);

        if ($status === 'generating_next') {
            $question = null;
            $qNum     = $answered + 1;
        } elseif ($awaiting && $answered > 0) {
            $question = $questions[$answered - 1] ?? null;
            $qNum     = $answered;
        } else {
            $question = $questions[$answered] ?? ($questions[$answered - 1] ?? null);
            $qNum     = $question ? ($awaiting ? $answered : $answered + 1) : max(1, $answered);
        }

        $lastSubmitted = ($awaiting && $answered > 0)
            ? ($answers[$answered - 1]['submitted_answer'] ?? null)
            : null;

        return [
            'status'                => $status,
            'session_id'            => $sessionId,
            'direction'             => $session['direction'] ?? '',
            'question'              => $question,
            'question_number'       => $qNum,
            'total_questions'       => self::MAX_QUESTIONS,
            'display_level'         => $session['display_level'] ?? 'determining',
            'current_streak'        => $session['current_streak'] ?? 0,
            'show_feedback'         => $awaiting,
            'last_is_correct'       => $session['ui']['last_is_correct'] ?? null,
            'last_submitted_answer' => $lastSubmitted,
            'awaiting_continue'     => $awaiting,
            'is_finished'           => false,
            'generating_next'       => $status === 'generating_next',
        ];
    }

    private function formatFinishedPayload(array $finished, string $sessionId, string $status): array
    {
        $payload = [
            'status'           => $status,
            'session_id'       => $sessionId,
            'is_finished'      => true,
            'result'           => $finished['result'] ?? null,
            'is_correct'       => $finished['is_correct'] ?? null,
            'assessment_uuid'  => $finished['assessment_uuid'] ?? null,
            'assessment_saved' => $finished['assessment_saved'] ?? false,
            'practical_task'   => $this->sanitizePracticalTaskForClient($finished['practical_task'] ?? []),
            'generating_task'  => $status === 'generating_task',
            'direction'        => $finished['direction'] ?? '',
        ];

        if ($status === 'generating_task') {
            $resume = $finished['resume'] ?? null;
            if (is_array($resume) && ! empty($resume['question'])) {
                $payload['question']              = $resume['question'];
                $payload['question_number']       = $resume['question_number'] ?? 1;
                $payload['show_feedback']         = (bool) ($resume['show_feedback'] ?? true);
                $payload['last_is_correct']       = $resume['last_is_correct'] ?? null;
                $payload['last_submitted_answer'] = $resume['last_submitted_answer'] ?? null;
                $payload['awaiting_continue']     = true;
            }
        }

        return $payload;
    }

    /** Убираем тест-кейсы из ответа API — студент отправляет код ментору. */
    private function sanitizePracticalTaskForClient(array $task): array
    {
        if ($task === []) {
            return [];
        }

        unset($task['test_cases'], $task['example_input'], $task['example_output']);

        return $task;
    }
}
