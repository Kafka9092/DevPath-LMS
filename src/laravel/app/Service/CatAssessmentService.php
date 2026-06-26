<?php

namespace App\Service;

use App\Models\CatAssessment;
use App\Models\CatCharacteristicScore;
use App\Models\CatCompetenceScore;
use App\Models\Characteristic;
use App\Models\Competence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CatAssessmentService
{
    private const MASTERED_THRESHOLD = 50;

    public function __construct(
        protected SonarQubeService $sonar,
    ) {}

    public function persistFromCatSession(
        int $userId,
        array $session,
        array $result,
        string $stopReason,
    ): string {
        $uuid = (string) Str::uuid();
        $direction = $this->normalizeDirection($session['direction'] ?? '');

        DB::transaction(function () use ($userId, $session, $result, $stopReason, $uuid, $direction) {
            $this->deleteBaselineAttempts($userId, $direction);

            $assessment = CatAssessment::create([
                'uuid'               => $uuid,
                'user_id'            => $userId,
                'direction'          => $direction,
                'overall_level'      => $result['overall_level'] ?? 'beginner',
                'stop_reason'        => $stopReason,
                'questions_answered' => count($session['answers'] ?? []),
                'weak_topics'        => $result['weak_topics'] ?? [],
                'strong_topics'      => $result['strong_topics'] ?? [],
                'recommendation'     => $result['recommendation'] ?? null,
                'finished_at'        => now(),
            ]);

            $this->syncCompetenceScores($assessment, $session['matrix'] ?? [], $result['competencies'] ?? []);
        });

        Log::info('CAT assessment persisted', ['user_id' => $userId, 'uuid' => $uuid]);

        return $uuid;
    }

    
    public function recordPracticalTask(
        string $assessmentUuid,
        ?string $code,
        bool $skipped = false,
    ): array {
        $assessment = CatAssessment::where('uuid', $assessmentUuid)->firstOrFail();

        if ($skipped || empty(trim($code ?? ''))) {
            $assessment->update(['practical_skipped' => true]);

            return [
                'is_complete'   => true,
                'code_feedback' => null,
                'skipped'       => true,
                'characteristics' => [],
            ];
        }

        $projectKey  = $this->sonar->saveCodeSnippet($code);
        $sonarResult = $this->sonar->analyzeProject($projectKey);

        $feedbackData = $this->buildPracticalFeedback($code, $sonarResult);

        DB::transaction(function () use ($assessment, $feedbackData) {
            $assessment->update([
                'practical_skipped'  => false,
                'practical_feedback' => [
                    'summary'      => $feedbackData['summary'] ?? null,
                    'strengths'    => $feedbackData['strengths'] ?? [],
                    'improvements' => $feedbackData['improvements'] ?? [],
                    'score'        => $feedbackData['score'] ?? null,
                ],
            ]);

            $this->syncCharacteristicScores($assessment, $feedbackData['characteristics'] ?? []);
        });

        return [
            'is_complete'     => true,
            'code_feedback'   => [
                'summary'      => $feedbackData['summary'] ?? '',
                'strengths'    => $feedbackData['strengths'] ?? [],
                'improvements' => $feedbackData['improvements'] ?? [],
                'score'        => $feedbackData['score'] ?? 0,
            ],
            'skipped'         => false,
            'characteristics' => $this->formatCharacteristicScores($assessment->fresh()),
        ];
    }

    public function linkToCourse(string $assessmentUuid, int $courseId): void
    {
        CatAssessment::where('uuid', $assessmentUuid)->update(['course_id' => $courseId]);
    }

    public function findLatestSeniorAssessment(int $userId, ?string $direction = null): ?CatAssessment
    {
        $query = CatAssessment::query()
            ->where('user_id', $userId)
            ->whereRaw('LOWER(overall_level) = ?', ['senior']);

        if ($direction !== null && $direction !== '') {
            $normalized = $this->normalizeDirection($direction);
            $query->where(function ($q) use ($normalized) {
                $q->where('direction', $normalized)
                    ->orWhereRaw('UPPER(direction) = ?', [$normalized]);
            });
        }

        return $query->orderByDesc('finished_at')->first();
    }

    public function resolveSeniorProgram(int $userId, ?string $direction = null): ?array
    {
        $assessment = $this->findLatestSeniorAssessment($userId, $direction);

        if (!$assessment) {
            return null;
        }

        return [
            'direction' => $this->normalizeDirection($assessment->direction),
        ];
    }

    public function userQualifiesForSeniorProgram(int $userId, ?string $direction = null): bool
    {
        return $this->findLatestSeniorAssessment($userId, $direction) !== null;
    }

    public function ensureSeniorPlanDraft(int $userId, ?string $direction = null): ?array
    {
        $draft = session('course_plan_draft', []);

        if (strtolower((string) ($draft['real_level'] ?? '')) === 'senior') {
            return [
                'direction' => $this->normalizeDirection((string) ($draft['direction'] ?? 'PHP')),
            ];
        }

        $assessment = $this->findLatestSeniorAssessment($userId, $direction);
        if (!$assessment) {
            return null;
        }

        $assessment->loadMissing(['competenceScores.competence']);

        $competences = [];
        foreach ($assessment->competenceScores as $row) {
            if ($row->competence) {
                $competences[$row->competence->name] = $row->score;
            }
        }

        $sessionPayload = [
            'course_plan_draft' => [
                'direction'      => $this->normalizeDirection($assessment->direction),
                'competences'    => $competences,
                'weak_topics'    => $assessment->weak_topics ?? [],
                'strong_topics'  => $assessment->strong_topics ?? [],
                'real_level'     => 'senior',
                'recommendation' => $assessment->recommendation ?? '',
                'from_test'      => true,
            ],
        ];

        if ($assessment->course_id === null) {
            $sessionPayload['cat_assessment_uuid'] = $assessment->uuid;
        }

        session($sessionPayload);

        return [
            'direction' => $this->normalizeDirection($assessment->direction),
        ];
    }

    private function normalizeDirection(string $direction): string
    {
        return strtoupper(trim($direction));
    }

    private function deleteBaselineAttempts(int $userId, string $direction): void
    {
        $direction = $this->normalizeDirection($direction);

        if ($direction === '') {
            return;
        }

        CatAssessment::query()
            ->where('user_id', $userId)
            ->whereNull('course_id')
            ->where(function ($query) use ($direction) {
                $query->where('direction', $direction)
                    ->orWhereRaw('UPPER(direction) = ?', [$direction]);
            })
            ->each(fn (CatAssessment $assessment) => $assessment->delete());
    }

    public function clearPlanSession(): void
    {
        session()->forget(['course_plan_draft', 'cat_assessment_uuid']);
    }

    public function getBaselineForCourse(int $userId, int $courseId): ?array
    {
        $assessment = CatAssessment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->with(['competenceScores.competence', 'characteristicScores.characteristic'])
            ->orderByDesc('finished_at')
            ->first();

        return $assessment ? $this->formatAssessment($assessment) : null;
    }

    private function syncCompetenceScores(CatAssessment $assessment, array $matrix, array $competencyMap): void
    {
        $scores = [];

        foreach ($matrix as $testedLevel => $competences) {
            foreach ($competences as $competenceId => $passed) {
                $id = (int) $competenceId;
                $score = $passed ? 100 : 0;
                $scores[$id] = [
                    'score'           => $score,
                    'mastered'        => $score >= self::MASTERED_THRESHOLD,
                    'tested_at_level' => $testedLevel,
                ];
            }
        }

        foreach ($competencyMap as $name => $score) {
            $competence = Competence::where('name', $name)->first();
            if (!$competence) {
                continue;
            }
            $intScore = (int) $score;
            if (!isset($scores[$competence->id]) || $scores[$competence->id]['score'] < $intScore) {
                $scores[$competence->id] = [
                    'score'           => $intScore,
                    'mastered'        => $intScore >= self::MASTERED_THRESHOLD,
                    'tested_at_level' => $assessment->overall_level,
                ];
            }
        }

        foreach ($scores as $competenceId => $data) {
            CatCompetenceScore::updateOrCreate(
                [
                    'cat_assessment_id' => $assessment->id,
                    'competence_id'     => $competenceId,
                ],
                $data,
            );
        }
    }

    private function syncCharacteristicScores(CatAssessment $assessment, array $characteristicScores): void
    {
        foreach ($characteristicScores as $name => $score) {
            $characteristic = Characteristic::where('name', $name)->first();
            if (!$characteristic) {
                continue;
            }

            CatCharacteristicScore::updateOrCreate(
                [
                    'cat_assessment_id' => $assessment->id,
                    'characteristic_id' => $characteristic->id,
                    'source'            => CatCharacteristicScore::SOURCE_PRACTICAL,
                ],
                [
                    'score'    => min(100, max(0, (int) $score)),
                    'feedback' => null,
                ],
            );
        }
    }

    private function buildPracticalFeedback(string $code, array $sonarResult): array
    {
        $issuesText = collect($sonarResult['issues'] ?? [])
            ->take(8)
            ->map(fn ($i) => ($i['severity'] ?? '') . ': ' . ($i['message'] ?? ''))
            ->implode("\n");

        $metricsJson = json_encode($sonarResult['metrics'] ?? [], JSON_UNESCAPED_UNICODE);

        $characteristicNames = Characteristic::pluck('name')->implode(', ');

        $prompt = <<<PROMPT
Оцени практический код кандидата.

Метрики SonarQube: {$metricsJson}

Замечания SonarQube:
{$issuesText}

Код:
```
{$code}
```

Верни ТОЛЬКО JSON:
{
  "summary": "краткий итог на русском",
  "strengths": ["..."],
  "improvements": ["..."],
  "score": 0-100,
  "characteristics": {
    "correctness": 0-100,
    "readability": 0-100,
    "code_structure": 0-100,
    "problem_solving": 0-100,
    "best_practices": 0-100
  }
}

Используй ключи characteristics строго из списка: {$characteristicNames}
PROMPT;

        try {
            return app(OllamaService::class)->generateJson($prompt);
        } catch (\Throwable $e) {
            Log::warning('Practical feedback AI failed: ' . $e->getMessage());

            $blocking = app(SonarQubeService::class)->hasBlockingIssues($sonarResult['issues'] ?? []);

            return [
                'summary'         => $blocking
                    ? 'Код требует исправления по замечаниям SonarQube.'
                    : 'Код принят по метрикам SonarQube (без AI-разбора).',
                'strengths'       => [],
                'improvements'    => array_column(array_slice($sonarResult['issues'] ?? [], 0, 3), 'message'),
                'score'           => $blocking ? 40 : 70,
                'characteristics' => [
                    'correctness'      => $blocking ? 40 : 70,
                    'readability'      => 50,
                    'code_structure'   => 50,
                    'problem_solving'  => 50,
                    'best_practices'   => $blocking ? 35 : 65,
                ],
            ];
        }
    }

    private function formatAssessment(CatAssessment $assessment): array
    {
        return [
            'uuid'               => $assessment->uuid,
            'direction'          => $assessment->direction,
            'overall_level'      => $assessment->overall_level,
            'stop_reason'        => $assessment->stop_reason,
            'questions_answered' => $assessment->questions_answered,
            'weak_topics'        => $assessment->weak_topics ?? [],
            'strong_topics'      => $assessment->strong_topics ?? [],
            'recommendation'     => $assessment->recommendation,
            'assessed_at'        => $assessment->finished_at?->toIso8601String(),
            'practical_skipped'  => $assessment->practical_skipped,
            'competences'        => $assessment->competenceScores->map(fn ($row) => [
                'id'              => $row->competence_id,
                'name'            => $row->competence->name,
                'category'        => $row->competence->category ?? null,
                'score'           => $row->score,
                'mastered'        => $row->mastered,
                'tested_at_level' => $row->tested_at_level,
            ])->values()->all(),
            'characteristics'    => $this->formatCharacteristicScores($assessment),
        ];
    }

    private function formatCharacteristicScores(CatAssessment $assessment): array
    {
        return $assessment->characteristicScores
            ->map(fn ($row) => [
                'name'  => $row->characteristic->name,
                'label' => $row->characteristic->label ?? $row->characteristic->name,
                'score' => $row->score,
            ])
            ->values()
            ->all();
    }
}
