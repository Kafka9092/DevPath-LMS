<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait BuildsCatTestSessions
{
    protected function catSessionId(): string
    {
        return (string) Str::uuid();
    }

    protected function seedCatSession(string $sessionId, array $session): void
    {
        Cache::put('test_session_' . $sessionId, $session, 3600);
    }

    protected function seedCatFinishedSession(string $sessionId, array $payload): void
    {
        Cache::put('test_finished_' . $sessionId, $payload, 3600);
    }

    /** @param list<array{0: string, 1: bool}> $series */
    protected function catAnswerSeries(array $series, int $competenceId = 1): array
    {
        $answers = [];
        foreach ($series as $i => [$level, $isCorrect]) {
            $answers[] = [
                'competence_id'    => $competenceId,
                'level'            => $level,
                'is_correct'       => $isCorrect,
                'submitted_answer' => $isCorrect ? 1 : 2,
                'question_id'      => $i + 1,
            ];
        }

        return $answers;
    }

    protected function catQuestion(int $id, int $competenceId = 1, int $correctIndex = 1): array
    {
        return [
            'id'            => $id,
            'type'          => 'single',
            'topic'         => 'test',
            'difficulty'    => 'junior',
            'text'          => "Question {$id}",
            'options'       => ['A', 'B', 'C', 'D'],
            'correct'       => [$correctIndex],
            'competence_id' => $competenceId,
        ];
    }

    protected function catBaseSession(array $overrides = []): array
    {
        return array_merge([
            'direction'                    => 'php',
            'questions'                    => [$this->catQuestion(1)],
            'answers'                      => [],
            'asked_by_level'               => ['beginner' => [], 'junior' => [], 'middle' => [], 'senior' => []],
            'asked_texts'                  => [],
            'current_question_level'       => 'junior',
            'display_level'                => 'determining',
            'lives'                        => ['beginner' => 2, 'junior' => 2, 'middle' => 2, 'senior' => 2],
            'ceiling'                      => 'senior',
            'current_streak'               => 0,
            'confirmed_level'              => null,
            'consecutive_correct_on_level' => 0,
            'beginner_fails'               => 0,
            'matrix'                       => [],
        ], $overrides);
    }
}
