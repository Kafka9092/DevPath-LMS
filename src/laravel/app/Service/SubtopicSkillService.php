<?php

namespace App\Service;

class SubtopicSkillService
{
    /** @var list<array{competence: string, subtopic_keywords: list<string>}>|null */
    private ?array $rules = null;

    /**
     * @return array{band: string, avg_score: int, matched: list<string>, from_cat: bool}
     */
    public function resolve(?string $subtopicTitle, ?array $catBaseline): array
    {
        if ($subtopicTitle === null || trim($subtopicTitle) === '') {
            return $this->neutralUnmatched($catBaseline);
        }

        if ($catBaseline === null) {
            return [
                'band'       => 'neutral',
                'avg_score'  => 50,
                'matched'    => [],
                'from_cat'   => false,
            ];
        }

        $titleLower = mb_strtolower(trim($subtopicTitle));
        $scores     = [];
        $matched    = [];

        foreach ($this->loadRules() as $rule) {
            if (!$this->titleMatchesRule($titleLower, $rule['subtopic_keywords'])) {
                continue;
            }

            $score = $this->scoreForCompetence($catBaseline, $rule['competence']);
            if ($score === null) {
                continue;
            }

            $matched[] = $rule['competence'];
            $scores[]  = $score;
        }

        if ($scores === []) {
            return $this->inferFromTopicLists($subtopicTitle, $catBaseline);
        }

        $avg = (int) round(array_sum($scores) / count($scores));

        return [
            'band'      => $this->bandFromScore($avg),
            'avg_score' => $avg,
            'matched'   => array_values(array_unique($matched)),
            'from_cat'  => true,
        ];
    }

    /**
     * @param list<string> $keywords
     */
    private function titleMatchesRule(string $titleLower, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $kw = mb_strtolower(trim($keyword));
            if ($kw !== '' && str_contains($titleLower, $kw)) {
                return true;
            }
        }

        return false;
    }

    private function scoreForCompetence(array $catBaseline, string $competenceName): ?int
    {
        $nameLower = mb_strtolower($competenceName);

        foreach ($catBaseline['competences'] ?? [] as $row) {
            $rowName = mb_strtolower((string) ($row['name'] ?? ''));
            if ($rowName === $nameLower || str_contains($rowName, $nameLower) || str_contains($nameLower, $rowName)) {
                return (int) ($row['score'] ?? 0);
            }
        }

        $strong = array_map('mb_strtolower', $catBaseline['strong_topics'] ?? []);
        $weak   = array_map('mb_strtolower', $catBaseline['weak_topics'] ?? []);

        if (in_array($nameLower, $strong, true)) {
            return 100;
        }
        if (in_array($nameLower, $weak, true)) {
            return 0;
        }

        return null;
    }

    /**
     * @return array{band: string, avg_score: int, matched: list<string>, from_cat: bool}
     */
    private function inferFromTopicLists(string $subtopicTitle, array $catBaseline): array
    {
        $titleLower = mb_strtolower($subtopicTitle);
        $strongHits = 0;
        $weakHits   = 0;

        foreach ($catBaseline['strong_topics'] ?? [] as $topic) {
            if ($this->fuzzyTopicMatch($titleLower, (string) $topic)) {
                $strongHits++;
            }
        }
        foreach ($catBaseline['weak_topics'] ?? [] as $topic) {
            if ($this->fuzzyTopicMatch($titleLower, (string) $topic)) {
                $weakHits++;
            }
        }

        if ($strongHits > $weakHits && $strongHits > 0) {
            return ['band' => 'strong', 'avg_score' => 85, 'matched' => [], 'from_cat' => true];
        }
        if ($weakHits > 0) {
            return ['band' => 'weak', 'avg_score' => 25, 'matched' => [], 'from_cat' => true];
        }

        return $this->neutralUnmatched($catBaseline);
    }

    private function fuzzyTopicMatch(string $titleLower, string $topic): bool
    {
        $topicLower = mb_strtolower(trim($topic));
        if ($topicLower === '') {
            return false;
        }

        if (str_contains($titleLower, $topicLower) || str_contains($topicLower, $titleLower)) {
            return true;
        }

        foreach (preg_split('/[\s\(\)\/\-:,]+/u', $topicLower) ?: [] as $token) {
            if (mb_strlen($token) >= 5 && str_contains($titleLower, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{band: string, avg_score: int, matched: list<string>, from_cat: bool}
     */
    private function neutralUnmatched(?array $catBaseline): array
    {
        return [
            'band'      => 'neutral',
            'avg_score' => 50,
            'matched'   => [],
            'from_cat'  => $catBaseline !== null,
        ];
    }

    private function bandFromScore(int $avg): string
    {
        if ($avg >= 70) {
            return 'strong';
        }
        if ($avg < 50) {
            return 'weak';
        }

        return 'neutral';
    }

    /**
     * @return list<array{competence: string, subtopic_keywords: list<string>}>
     */
    private function loadRules(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $path = database_path('data/competence_subtopic_map.json');
        if (!is_file($path)) {
            $this->rules = [];

            return $this->rules;
        }

        $data = json_decode((string) file_get_contents($path), true);
        $this->rules = is_array($data['rules'] ?? null) ? $data['rules'] : [];

        return $this->rules;
    }
}
