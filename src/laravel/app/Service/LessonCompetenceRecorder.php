<?php

namespace App\Service;

use App\Models\CatAssessment;
use App\Models\CatCharacteristicScore;
use App\Models\CatCompetenceScore;
use App\Models\Characteristic;
use App\Models\Competence;
use App\Models\Subtopic;
use Illuminate\Support\Facades\Log;

class LessonCompetenceRecorder
{
    private const MASTERED_THRESHOLD = 80;

    public function __construct(
        protected OllamaService $ollama,
    ) {}

    public function recordLessonOutcome(
        int $userId,
        int $courseId,
        Subtopic $subtopic,
        string $code,
        array $aiReview,
    ): void {
        $assessment = CatAssessment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->orderByDesc('finished_at')
            ->first();

        if (!$assessment) {
            return;
        }

        if (!$this->ollama->isAvailable()) {
            $this->applyFallbackScores($assessment, (int) ($aiReview['score'] ?? 70));

            return;
        }

        $competenceNames = Competence::query()->pluck('name')->take(40)->implode(', ');
        $characteristicNames = Characteristic::pluck('name')->implode(', ');

        $isCorrect = ($aiReview['is_correct'] ?? false) ? 'да' : 'нет';
        $score     = (int) ($aiReview['score'] ?? 0);
        $feedback  = $aiReview['feedback'] ?? '';

        $prompt = <<<PROMPT
Оцени результат урока по подтеме «{$subtopic->title}».
Оценка решения: {$score}/100, верно: {$isCorrect}.
Краткий фидбек: {$feedback}

Фрагмент кода студента (начало):
```
{$this->truncateCode($code)}
```

Верни ТОЛЬКО JSON:
{
  "competencies": { "имя компетенции из списка": 0-100 },
  "characteristics": { "имя характеристики из списка": 0-100 }
}

Компетенции (используй только подходящие имена): {$competenceNames}
Характеристики (строго ключи из списка): {$characteristicNames}
PROMPT;

        try {
            $data = $this->ollama->generateJson($prompt);
            $this->syncCompetencies($assessment, $data['competencies'] ?? []);
            $this->syncCharacteristics($assessment, $data['characteristics'] ?? []);
        } catch (\Throwable $e) {
            Log::warning('Lesson competence recording failed: ' . $e->getMessage());
            $this->applyFallbackScores($assessment, (int) ($aiReview['score'] ?? 70));
        }
    }

    private function syncCompetencies(CatAssessment $assessment, array $competencyMap): void
    {
        foreach ($competencyMap as $name => $score) {
            $competence = Competence::where('name', $name)->first();
            if (!$competence) {
                continue;
            }

            $intScore = min(100, max(0, (int) $score));
            $existing = CatCompetenceScore::where('cat_assessment_id', $assessment->id)
                ->where('competence_id', $competence->id)
                ->first();

            $merged = $existing
                ? (int) round(($existing->score + $intScore) / 2)
                : $intScore;

            CatCompetenceScore::updateOrCreate(
                [
                    'cat_assessment_id' => $assessment->id,
                    'competence_id'     => $competence->id,
                ],
                [
                    'score'           => $merged,
                    'mastered'        => $merged >= self::MASTERED_THRESHOLD,
                    'tested_at_level' => $assessment->overall_level,
                ],
            );
        }
    }

    private function syncCharacteristics(CatAssessment $assessment, array $characteristicScores): void
    {
        foreach ($characteristicScores as $name => $score) {
            $characteristic = Characteristic::where('name', $name)->first();
            if (!$characteristic) {
                continue;
            }

            $intScore = min(100, max(0, (int) $score));

            $existing = CatCharacteristicScore::where('cat_assessment_id', $assessment->id)
                ->where('characteristic_id', $characteristic->id)
                ->where('source', CatCharacteristicScore::SOURCE_PRACTICAL)
                ->first();

            $merged = $existing
                ? (int) round(($existing->score + $intScore) / 2)
                : $intScore;

            CatCharacteristicScore::updateOrCreate(
                [
                    'cat_assessment_id' => $assessment->id,
                    'characteristic_id' => $characteristic->id,
                    'source'            => CatCharacteristicScore::SOURCE_PRACTICAL,
                ],
                [
                    'score'    => $merged,
                    'feedback' => null,
                ],
            );
        }
    }

    private function applyFallbackScores(CatAssessment $assessment, int $lessonScore): void
    {
        foreach ($assessment->competenceScores as $row) {
            $merged = (int) round(($row->score + $lessonScore) / 2);
            $row->update([
                'score'    => $merged,
                'mastered' => $merged >= self::MASTERED_THRESHOLD,
            ]);
        }
    }

    private function truncateCode(string $code): string
    {
        return mb_substr($code, 0, 2500);
    }
}
