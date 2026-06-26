<?php

namespace App\Service;

use App\Models\CatAssessment;
use App\Models\CodeReview;
use App\Models\Course;
use App\Models\HrInterview;
use App\Models\UserProgressSubtopic;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Статистика для страницы Progress.
 * Всё из БД — Kafka тут не нужен, просто агрегируем таблицы.
 */
class ProgressStatsService
{
    private const ACTIVITY_DAYS = 30;

    /** Языки, доступные при создании курса (полный список платформы). */
    private const PLATFORM_LANGUAGES = [
        'PHP', 'Python', 'JavaScript', 'TypeScript', 'Java', 'C++', 'C#', 'Go', 'Ruby',
    ];

    /** Главный метод — собирает overview + learning + interviews + codeReview. */
    public function build(?int $userId): array
    {
        if (!$userId) {
            return $this->emptyPayload();
        }

        $baselinesByLang = $this->buildLanguageBaselines($userId);

        $learning   = $this->learningStats($userId, $baselinesByLang);
        $interviews = $this->interviewStats($userId, $baselinesByLang);
        $codeReview = $this->codeReviewStats($userId, $baselinesByLang);
        $activity   = $this->activityTimeline($userId, $baselinesByLang);
        $languages  = $this->languageSummary($learning, $interviews, $codeReview);

        $languageStats = $this->buildLanguageStats($userId, $baselinesByLang);

        return [
            'languages' => $languageStats,
            'overview'  => [
                'total_actions'   => $learning['summary']['lessons_touched']
                    + $interviews['summary']['total']
                    + $codeReview['summary']['total'],
                'active_days'     => $this->countActiveDays($activity),
                'languages'       => $languages,
                'activity'        => $activity,
                'courses_count'   => $learning['summary']['courses_active'],
                'learning_pct'    => $this->percentPart($learning['summary']['lessons_completed'], $learning['summary']['lessons_touched']),
                'interview_pct'   => $this->percentPart($interviews['summary']['completed'], max(1, $interviews['summary']['total'])),
                'code_review_pct' => $this->percentPart($codeReview['summary']['high_score_count'], max(1, $codeReview['summary']['total'])),
            ],
            'learning'   => $learning,
            'interviews' => $interviews,
            'codeReview' => $codeReview,
        ];
    }

    private function emptyPayload(): array
    {
        return [
            'languages' => [],
            'overview'  => [
                'total_actions'   => 0,
                'active_days'     => 0,
                'languages'       => [],
                'activity'        => $this->emptyActivityDays(),
                'courses_count'   => 0,
                'learning_pct'    => 0,
                'interview_pct'   => 0,
                'code_review_pct' => 0,
            ],
            'learning'   => $this->emptyLearning(),
            'interviews' => $this->emptyInterviews(),
            'codeReview' => $this->emptyCodeReview(),
        ];
    }

    
    private function buildLanguageBaselines(int $userId): array
    {
        $languages = $this->collectUserLanguages($userId);
        $baselines = [];

        $assessmentsByLang = CatAssessment::query()
            ->where('user_id', $userId)
            ->whereNotNull('finished_at')
            ->orderBy('finished_at')
            ->get()
            ->groupBy(fn ($a) => $this->prettyDirection($a->direction));

        $progressRows = UserProgressSubtopic::query()
            ->where('user_id', $userId)
            ->with(['course.direction'])
            ->get();

        $coursesPerLang = Course::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->with('direction')
            ->get()
            ->groupBy(fn ($c) => $this->courseLanguage($c))
            ->map->count();

        foreach ($languages as $lang) {
            if ($lang === '—') {
                continue;
            }

            
            $test = $assessmentsByLang->get($lang)?->first();

            if ($test) {
                $baselines[$lang] = [
                    'language'      => $lang,
                    'source'        => 'test',
                    'at'            => $test->finished_at->toIso8601String(),
                    'level'         => $this->prettyLevel($test->overall_level),
                    'label'         => 'Адаптивный тест',
                    'courses_count' => (int) ($coursesPerLang[$lang] ?? 0),
                ];
                continue;
            }

            $firstLesson = $progressRows
                ->filter(fn ($r) => $this->courseLanguage($r->course) === $lang)
                ->sortBy('created_at')
                ->first();

            if ($firstLesson) {
                $baselines[$lang] = [
                    'language'      => $lang,
                    'source'        => 'learning',
                    'at'            => $firstLesson->created_at->toIso8601String(),
                    'level'         => null,
                    'label'         => 'Первый урок',
                    'courses_count' => (int) ($coursesPerLang[$lang] ?? 0),
                ];
                continue;
            }

            $firstInterview = HrInterview::query()
                ->where('user_id', $userId)
                ->get()
                ->first(fn ($i) => $this->prettyDirection($i->direction) === $lang)
                ?->created_at;

            $firstReview = CodeReview::query()
                ->where('user_id', $userId)
                ->orderBy('created_at')
                ->get()
                ->first(fn ($r) => $this->prettyDirection($r->detected_language) === $lang);

            $at = collect([
                $firstInterview ? Carbon::parse($firstInterview) : null,
                $firstReview?->created_at,
            ])->filter()->sort()->first();

            if ($at) {
                $baselines[$lang] = [
                    'language'      => $lang,
                    'source'        => 'activity',
                    'at'            => $at->toIso8601String(),
                    'level'         => null,
                    'label'         => 'Первая активность',
                    'courses_count' => (int) ($coursesPerLang[$lang] ?? 0),
                ];
            }
        }

        return $baselines;
    }

    
    private function collectUserLanguages(int $userId): array
    {
        $langs = collect();

        CatAssessment::query()
            ->where('user_id', $userId)
            ->pluck('direction')
            ->each(fn ($d) => $langs->push($this->prettyDirection($d)));

        UserProgressSubtopic::query()
            ->where('user_id', $userId)
            ->with('course.direction')
            ->get()
            ->each(fn ($r) => $langs->push($this->courseLanguage($r->course)));

        HrInterview::query()
            ->where('user_id', $userId)
            ->pluck('direction')
            ->each(fn ($d) => $langs->push($this->prettyDirection($d)));

        CodeReview::query()
            ->where('user_id', $userId)
            ->pluck('detected_language')
            ->each(fn ($d) => $langs->push($this->prettyDirection($d)));

        return $langs->filter(fn ($l) => $l !== '—')->unique()->sort()->values()->all();
    }

    private function passesBaseline(array $baselinesByLang, string $lang, Carbon $at): bool
    {
        if (!isset($baselinesByLang[$lang])) {
            return true;
        }

        return $at->greaterThanOrEqualTo(Carbon::parse($baselinesByLang[$lang]['at']));
    }

    private function learningStats(int $userId, array $baselinesByLang): array
    {
        $rows = UserProgressSubtopic::query()
            ->where('user_id', $userId)
            ->with(['course.direction', 'subtopic'])
            ->get()
            ->filter(function ($r) use ($baselinesByLang) {
                $lang = $this->courseLanguage($r->course);

                return $this->passesBaseline($baselinesByLang, $lang, $r->created_at);
            });

        $completed = $rows->where('is_completed', true);
        $scores    = $completed->pluck('lesson_score')->filter(fn ($s) => $s !== null);

        $byLanguage = $rows->groupBy(fn ($r) => $this->courseLanguage($r->course))
            ->map(function (Collection $group, string $lang) use ($baselinesByLang) {
                $done = $group->where('is_completed', true);
                $base = $baselinesByLang[$lang] ?? null;

                return [
                    'language'           => $lang,
                    'lessons_touched'    => $group->count(),
                    'lessons_completed'  => $done->count(),
                    'avg_score'          => round((float) $done->avg('lesson_score'), 1) ?: null,
                    'hints_used'         => (int) $group->sum('hints_used'),
                    'completion_rate'    => $group->count() > 0
                        ? round(100 * $done->count() / $group->count(), 1)
                        : 0,
                    'courses_count'      => $base['courses_count'] ?? $group->pluck('course_id')->unique()->count(),
                ];
            })
            ->values()
            ->all();

        $byLanguage = $this->mergeLearningByLanguage($byLanguage);

        $courseProgressPct = $rows->count() > 0
            ? round(100 * $completed->count() / $rows->count(), 1)
            : 0;

        return [
            'summary' => [
                'lessons_touched'     => $rows->count(),
                'lessons_completed'   => $completed->count(),
                'completion_rate'     => $courseProgressPct,
                'course_progress_pct' => $courseProgressPct,
                'avg_score'           => $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null,
                'hints_used'          => (int) $rows->sum('hints_used'),
                'courses_active'      => $rows->pluck('course_id')->unique()->count(),
            ],
            'by_language'   => $byLanguage,
            'score_buckets' => $this->scoreBuckets($scores),
            'recent'        => $completed->sortByDesc('completed_at')->take(5)->map(fn ($r) => [
                'title'    => $r->task_title ?: ($r->subtopic?->title ?? 'Урок'),
                'language' => $this->courseLanguage($r->course),
                'score'    => $r->lesson_score,
                'date'     => $r->completed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function interviewStats(int $userId, array $baselinesByLang): array
    {
        $rows = HrInterview::query()
            ->where('user_id', $userId)
            ->withCount('messages')
            ->get()
            ->filter(function ($i) use ($baselinesByLang) {
                $lang = $this->prettyDirection($i->direction);

                return $this->passesBaseline($baselinesByLang, $lang, $i->created_at);
            });

        $completed = $rows->where('status', 'completed');
        $decisions = $completed->map(fn ($i) => strtolower((string) ($i->verdict['decision'] ?? '')));

        $successful = $decisions->filter(fn ($d) => in_array($d, ['hire', 'strong_hire', 'yes'], true))->count();
        $rejected   = $decisions->filter(fn ($d) => in_array($d, ['no_hire', 'reject', 'no'], true))->count();

        $byLanguage = $rows->groupBy(fn ($i) => $this->prettyDirection($i->direction))
            ->map(function (Collection $group, string $lang) {
                $done = $group->where('status', 'completed');
                $dec  = $done->map(fn ($i) => strtolower((string) ($i->verdict['decision'] ?? '')));
                $ok   = $dec->filter(fn ($d) => in_array($d, ['hire', 'strong_hire', 'yes'], true))->count();

                return [
                    'language'        => $lang,
                    'total'           => $group->count(),
                    'completed'       => $done->count(),
                    'successful'      => $ok,
                    'success_rate'    => $done->count() > 0 ? round(100 * $ok / $done->count(), 1) : 0,
                    'avg_duration_min'=> round((float) $done->avg('duration_seconds') / 60, 1) ?: null,
                ];
            })
            ->values()
            ->all();

        $byLanguage = $this->mergeInterviewByLanguage($byLanguage);

        $byLevel = $rows->groupBy('level')
            ->map(fn (Collection $g, $lvl) => [
                'level'     => $lvl,
                'total'     => $g->count(),
                'completed' => $g->where('status', 'completed')->count(),
            ])
            ->values()
            ->sortByDesc('total')
            ->values()
            ->all();

        $starAvg = $this->averageStarScores($completed);
        $avgDuration = round((float) $completed->avg('duration_seconds') / 60, 1) ?: null;

        return [
            'summary' => [
                'total'                  => $rows->count(),
                'completed'              => $completed->count(),
                'stopped_early'          => $rows->where('status', 'stopped_early')->count(),
                'successful'             => $successful,
                'rejected'               => $rejected,
                'success_rate'           => $completed->count() > 0
                    ? round(100 * $successful / $completed->count(), 1)
                    : 0,
                'avg_duration_min'       => $avgDuration,
                'avg_messages'           => round((float) $rows->avg('messages_count'), 1) ?: null,
                'code_tasks'             => (int) DB::table('hr_interview_messages')
                    ->whereIn('interview_id', $rows->pluck('id'))
                    ->where('has_code_task', true)
                    ->count(),
                'ai_hard_skills_score'   => $this->averageHardSkillsScore($completed),
                'weak_topics'            => $this->aggregateInterviewWeakTopics($completed),
            ],
            'by_language' => $byLanguage,
            'by_level'    => $byLevel,
            'decisions'   => [
                ['label' => 'Успешные', 'value' => $successful, 'color' => '#10b981'],
                ['label' => 'Отказы', 'value' => $rejected, 'color' => '#ef4444'],
                ['label' => 'Другое', 'value' => max(0, $completed->count() - $successful - $rejected), 'color' => '#94a3b8'],
            ],
            'star_scores' => $starAvg,
            'recent'      => $completed->sortByDesc('finished_at')->take(5)->map(fn ($i) => [
                'direction' => $this->prettyDirection($i->direction),
                'level'     => $i->level,
                'decision'  => $i->verdict['decision'] ?? '—',
                'date'      => $i->finished_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function codeReviewStats(int $userId, array $baselinesByLang): array
    {
        $rows = CodeReview::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->filter(function ($r) use ($baselinesByLang) {
                $lang = $this->prettyDirection($r->detected_language);

                return $this->passesBaseline($baselinesByLang, $lang, $r->created_at);
            });

        $scores = $rows->pluck('overall_score')->filter(fn ($s) => $s !== null);

        $byLanguage = $rows->groupBy(fn ($r) => $this->prettyDirection($r->detected_language))
            ->map(function (Collection $group, string $lang) {
                $sc = $group->pluck('overall_score')->filter(fn ($s) => $s !== null);

                return [
                    'language'  => $lang,
                    'total'     => $group->count(),
                    'avg_score' => $sc->isNotEmpty() ? round((float) $sc->avg(), 1) : null,
                    'best'      => $sc->isNotEmpty() ? (int) $sc->max() : null,
                ];
            })
            ->values()
            ->all();

        $byLanguage = $this->mergeReviewByLanguage($byLanguage);

        $highScore = $scores->filter(fn ($s) => $s >= 70)->count();
        $totalIssues = (int) $rows->sum(fn ($r) => is_array($r->sonar_issues) ? count($r->sonar_issues) : 0);
        $totalLoc = max(1, (int) $rows->sum(fn ($r) => $this->estimateReviewLoc($r)));

        return [
            'summary' => [
                'total'               => $rows->count(),
                'avg_score'           => $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null,
                'best_score'          => $scores->isNotEmpty() ? (int) $scores->max() : null,
                'high_score_count'    => $highScore,
                'high_score_rate'     => $rows->count() > 0
                    ? round(100 * $highScore / $rows->count(), 1)
                    : 0,
                'total_issues'        => $totalIssues,
                'clean_code_index'    => $scores->isNotEmpty() ? (int) round((float) $scores->avg()) : null,
                'issues_per_100_loc'  => round(100 * $totalIssues / $totalLoc, 1),
                'critical_categories' => $this->aggregateReviewCriticalCategories($rows),
            ],
            'by_language'   => $byLanguage,
            'score_buckets' => $this->reviewScoreBuckets($scores),
            'score_trend'   => $this->weeklyScoreTrend($rows),
            'recent'        => $rows->take(5)->map(fn ($r) => [
                'language' => $this->prettyDirection($r->detected_language),
                'score'    => $r->overall_score,
                'grade'    => $r->ai_evaluation['grade_label'] ?? null,
                'date'     => $r->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function activityTimeline(int $userId, array $baselinesByLang): array
    {
        $days = $this->emptyActivityDays();
        $keys = collect($days)->pluck('date')->flip();

        $bump = function (string $date, string $field, int $count = 1) use (&$days, $keys) {
            if (!$keys->has($date) || $count < 1) {
                return;
            }
            $idx = $keys[$date];
            $days[$idx][$field] += $count;
            $days[$idx]['total'] += $count;
        };

        UserProgressSubtopic::query()
            ->where('user_id', $userId)
            ->with('course.direction')
            ->get()
            ->filter(function ($r) use ($baselinesByLang) {
                return $this->passesBaseline(
                    $baselinesByLang,
                    $this->courseLanguage($r->course),
                    $r->updated_at,
                );
            })
            ->groupBy(fn ($r) => $r->updated_at->format('Y-m-d'))
            ->each(fn (Collection $g, string $d) => $bump($d, 'learning', $g->count()));

        HrInterview::query()
            ->where('user_id', $userId)
            ->get()
            ->filter(function ($i) use ($baselinesByLang) {
                return $this->passesBaseline(
                    $baselinesByLang,
                    $this->prettyDirection($i->direction),
                    $i->created_at,
                );
            })
            ->groupBy(fn ($i) => $i->created_at->format('Y-m-d'))
            ->each(fn (Collection $g, string $d) => $bump($d, 'interviews', $g->count()));

        CodeReview::query()
            ->where('user_id', $userId)
            ->get()
            ->filter(function ($r) use ($baselinesByLang) {
                return $this->passesBaseline(
                    $baselinesByLang,
                    $this->prettyDirection($r->detected_language),
                    $r->created_at,
                );
            })
            ->groupBy(fn ($r) => $r->created_at->format('Y-m-d'))
            ->each(fn (Collection $g, string $d) => $bump($d, 'code_review', $g->count()));

        return $days;
    }

    private function emptyActivityDays(): array
    {
        $days = [];
        for ($i = self::ACTIVITY_DAYS - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $days[] = [
                'date'        => $date,
                'label'       => now()->subDays($i)->locale('ru')->isoFormat('D MMM'),
                'learning'    => 0,
                'interviews'  => 0,
                'code_review' => 0,
                'total'       => 0,
            ];
        }

        return $days;
    }

    private function languageSummary(array $learning, array $interviews, array $codeReview): array
    {
        $map = [];

        foreach ($learning['by_language'] as $row) {
            $lang = $row['language'];
            $map[$lang] = ($map[$lang] ?? 0) + $row['lessons_touched'];
        }
        foreach ($interviews['by_language'] as $row) {
            $lang = $row['language'];
            $map[$lang] = ($map[$lang] ?? 0) + $row['total'];
        }
        foreach ($codeReview['by_language'] as $row) {
            $lang = $row['language'];
            $map[$lang] = ($map[$lang] ?? 0) + $row['total'];
        }

        return collect($this->platformLanguages())
            ->map(fn (string $lang) => [
                'language' => $lang,
                'actions'  => (int) ($map[$lang] ?? 0),
            ])
            ->all();
    }

    private function platformLanguages(): array
    {
        return self::PLATFORM_LANGUAGES;
    }

    private function mergeLearningByLanguage(array $rows): array
    {
        $indexed = collect($rows)->keyBy('language');

        return collect($this->platformLanguages())
            ->map(fn (string $lang) => $indexed->get($lang) ?? [
                'language'          => $lang,
                'lessons_touched'   => 0,
                'lessons_completed' => 0,
                'avg_score'         => null,
                'hints_used'        => 0,
                'completion_rate'   => 0,
                'courses_count'     => 0,
            ])
            ->all();
    }

    private function mergeInterviewByLanguage(array $rows): array
    {
        $indexed = collect($rows)->keyBy('language');

        return collect($this->platformLanguages())
            ->map(fn (string $lang) => $indexed->get($lang) ?? [
                'language'         => $lang,
                'total'            => 0,
                'completed'        => 0,
                'successful'       => 0,
                'success_rate'     => 0,
                'avg_duration_min' => null,
            ])
            ->all();
    }

    private function mergeReviewByLanguage(array $rows): array
    {
        $indexed = collect($rows)->keyBy('language');

        return collect($this->platformLanguages())
            ->map(fn (string $lang) => $indexed->get($lang) ?? [
                'language'  => $lang,
                'total'     => 0,
                'avg_score' => null,
                'best'      => null,
            ])
            ->all();
    }

    private function countActiveDays(array $activity): int
    {
        return collect($activity)->where('total', '>', 0)->count();
    }

    private function courseLanguage(?Course $course): string
    {
        if (!$course) {
            return '—';
        }

        return $this->prettyDirection($course->direction?->name ?? $course->title);
    }

    private function prettyDirection(?string $value): string
    {
        if (!$value) {
            return '—';
        }

        $v = trim($value);

        return match (strtolower($v)) {
            'php'          => 'PHP',
            'python'       => 'Python',
            'javascript', 'js' => 'JavaScript',
            'typescript', 'ts' => 'TypeScript',
            'java'         => 'Java',
            'c++', 'cpp'   => 'C++',
            'c#', 'csharp' => 'C#',
            'go', 'golang' => 'Go',
            'ruby'         => 'Ruby',
            default        => ucfirst($v),
        };
    }

    private function prettyLevel(?string $level): string
    {
        if (!$level) {
            return '—';
        }

        return match (strtolower($level)) {
            'beginner' => 'Beginner',
            'junior'   => 'Junior',
            'middle', 'mid' => 'Middle',
            'senior'   => 'Senior',
            default    => ucfirst($level),
        };
    }

    private function percentPart(int $part, int $whole): float
    {
        if ($whole <= 0) {
            return 0;
        }

        return min(100, round(100 * $part / $whole, 1));
    }

    private function scoreBuckets(Collection $scores): array
    {
        $buckets = [
            ['label' => '0–39', 'min' => 0, 'max' => 39, 'count' => 0],
            ['label' => '40–59', 'min' => 40, 'max' => 59, 'count' => 0],
            ['label' => '60–79', 'min' => 60, 'max' => 79, 'count' => 0],
            ['label' => '80–100', 'min' => 80, 'max' => 100, 'count' => 0],
        ];

        foreach ($scores as $score) {
            foreach ($buckets as &$b) {
                if ($score >= $b['min'] && $score <= $b['max']) {
                    $b['count']++;
                    break;
                }
            }
        }
        unset($b);

        return $buckets;
    }

    private function reviewScoreBuckets(Collection $scores): array
    {
        return $this->scoreBuckets($scores);
    }

    private function weeklyScoreTrend(Collection $rows): array
    {
        return $rows->groupBy(fn ($r) => $r->created_at->startOfWeek()->format('Y-m-d'))
            ->sortKeys()
            ->take(-8)
            ->map(fn (Collection $g, $week) => [
                'week'  => Carbon::parse($week)->locale('ru')->isoFormat('D MMM'),
                'avg'   => round((float) $g->avg('overall_score'), 1) ?: 0,
                'count' => $g->count(),
            ])
            ->values()
            ->all();
    }

    private function averageHardSkillsScore(Collection $completed): ?float
    {
        if ($completed->isEmpty()) {
            return null;
        }

        $scores = $completed->map(function ($interview) {
            $stars = $interview->verdict['star_scores'] ?? [];
            if (!is_array($stars)) {
                return null;
            }
            $vals = collect(['action', 'result'])
                ->map(fn ($k) => isset($stars[$k]) && is_numeric($stars[$k]) ? (float) $stars[$k] : null)
                ->filter();
            if ($vals->isEmpty()) {
                return null;
            }

            return round($vals->avg() / 5 * 100, 1);
        })->filter();

        return $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null;
    }

    private function averageSoftSkillsScore(Collection $completed): ?float
    {
        if ($completed->isEmpty()) {
            return null;
        }

        $scores = $completed->map(function ($interview) {
            $stars = $interview->verdict['star_scores'] ?? [];
            if (!is_array($stars)) {
                return null;
            }
            $vals = collect(['situation', 'task'])
                ->map(fn ($k) => isset($stars[$k]) && is_numeric($stars[$k]) ? (float) $stars[$k] : null)
                ->filter();
            if ($vals->isEmpty()) {
                return null;
            }

            return round($vals->avg() / 5 * 100, 1);
        })->filter();

        return $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null;
    }

    
    private function aggregateInterviewWeakTopics(Collection $completed): array
    {
        $counts = [];
        foreach ($completed as $interview) {
            $weak = $interview->verdict['weaknesses'] ?? [];
            if (!is_array($weak)) {
                continue;
            }
            foreach ($weak as $topic) {
                $t = trim((string) $topic);
                if ($t === '') {
                    continue;
                }
                $counts[$t] = ($counts[$t] ?? 0) + 1;
            }
        }

        arsort($counts);

        return array_slice(array_keys($counts), 0, 5);
    }

    private function estimateReviewLoc(CodeReview $review): int
    {
        $code = trim((string) $review->code);
        if ($code === '') {
            return 80;
        }

        $lines = preg_split('/\r\n|\r|\n/', $code);

        return max(10, count(array_filter($lines, fn ($l) => trim($l) !== '')));
    }

    
    private function aggregateReviewCriticalCategories(Collection $rows): array
    {
        $counts = [
            'Архитектура'   => 0,
            'Безопасность'  => 0,
            'Чистота кода'  => 0,
            'Форматирование'=> 0,
        ];

        foreach ($rows as $review) {
            $issues = is_array($review->sonar_issues) ? $review->sonar_issues : [];
            foreach ($issues as $issue) {
                $type = strtoupper((string) ($issue['type'] ?? ''));
                $rule = strtolower((string) ($issue['rule'] ?? $issue['message'] ?? ''));
                if (str_contains($type, 'VULNER') || str_contains($rule, 'security')) {
                    $counts['Безопасность']++;
                } elseif (str_contains($type, 'BUG')) {
                    $counts['Архитектура']++;
                } elseif (str_contains($type, 'SMELL') || str_contains($rule, 'design')) {
                    $counts['Чистота кода']++;
                } else {
                    $counts['Форматирование']++;
                }
            }
        }

        return collect($counts)
            ->map(fn ($count, $label) => ['label' => $label, 'count' => $count])
            ->filter(fn ($row) => $row['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->take(4)
            ->all();
    }

    private function averageStarScores(Collection $completed): array
    {
        $keys   = ['situation', 'task', 'action', 'result'];
        $totals = array_fill_keys($keys, 0.0);
        $counts = array_fill_keys($keys, 0);

        foreach ($completed as $interview) {
            $stars = $interview->verdict['star_scores'] ?? [];
            if (!is_array($stars)) {
                continue;
            }
            foreach ($keys as $k) {
                if (isset($stars[$k]) && is_numeric($stars[$k])) {
                    $totals[$k] += (float) $stars[$k];
                    $counts[$k]++;
                }
            }
        }

        return collect($keys)->map(fn ($k) => [
            'label' => strtoupper($k[0]),
            'key'   => $k,
            'avg'   => $counts[$k] > 0 ? round($totals[$k] / $counts[$k], 2) : 0,
        ])->all();
    }

    private function emptyLearning(): array
    {
        return [
            'summary' => [
                'lessons_touched' => 0, 'lessons_completed' => 0, 'completion_rate' => 0,
                'course_progress_pct' => 0,
                'avg_score' => null, 'hints_used' => 0, 'courses_active' => 0,
            ],
            'by_language' => $this->mergeLearningByLanguage([]), 'score_buckets' => [], 'recent' => [],
        ];
    }

    private function emptyInterviews(): array
    {
        return [
            'summary' => [
                'total' => 0, 'completed' => 0, 'stopped_early' => 0,
                'successful' => 0, 'rejected' => 0, 'success_rate' => 0,
                'avg_duration_min' => null, 'avg_messages' => null, 'code_tasks' => 0,
                'ai_hard_skills_score' => null,
                'weak_topics' => [],
            ],
            'by_language' => $this->mergeInterviewByLanguage([]), 'by_level' => [],
            'decisions' => [], 'star_scores' => [], 'recent' => [],
        ];
    }

    private function emptyCodeReview(): array
    {
        return [
            'summary' => [
                'total' => 0, 'avg_score' => null, 'best_score' => null,
                'high_score_count' => 0, 'high_score_rate' => 0, 'total_issues' => 0,
                'clean_code_index' => null, 'issues_per_100_loc' => 0,
                'critical_categories' => [],
            ],
            'by_language' => $this->mergeReviewByLanguage([]), 'score_buckets' => [], 'score_trend' => [], 'recent' => [],
        ];
    }

    
    private function buildLanguageStats(int $userId, array $baselinesByLang): array
    {
        $stats = [];

        foreach ($this->platformLanguages() as $lang) {
            $baseline   = $baselinesByLang[$lang] ?? null;
            $startAt    = $baseline ? Carbon::parse($baseline['at']) : now()->subWeeks(12);
            $startValue = $this->startIndexForBaseline($baseline);

            $lessonRows = UserProgressSubtopic::query()
                ->where('user_id', $userId)
                ->with('course.direction')
                ->get()
                ->filter(fn ($r) => $this->courseLanguage($r->course) === $lang)
                ->filter(fn ($r) => $this->passesBaseline($baselinesByLang, $lang, $r->created_at));

            $interviewRows = HrInterview::query()
                ->where('user_id', $userId)
                ->get()
                ->filter(fn ($i) => $this->prettyDirection($i->direction) === $lang)
                ->filter(fn ($i) => $this->passesBaseline($baselinesByLang, $lang, $i->created_at));

            $reviewRows = CodeReview::query()
                ->where('user_id', $userId)
                ->get()
                ->filter(fn ($r) => $this->prettyDirection($r->detected_language) === $lang)
                ->filter(fn ($r) => $this->passesBaseline($baselinesByLang, $lang, $r->created_at));

            $lessonsCompleted = $lessonRows->where('is_completed', true)->count();
            $lessonsTotal     = $lessonRows->count();
            $interviewsCount  = $interviewRows->count();
            $reviewsCount     = $reviewRows->count();
            $hasActivity      = $lessonsTotal > 0 || $interviewsCount > 0 || $reviewsCount > 0;

            $moduleScores = $this->computeLanguageModuleScores($lessonRows, $interviewRows, $reviewRows);
            $composite    = $this->computeLanguageProgressIndex(
                $moduleScores['learning'],
                $moduleScores['interviews'],
                $moduleScores['review'],
            );

            $current = $hasActivity ? $composite : $startValue;
            $delta   = round($current - $startValue, 1);

            $events   = $this->collectLanguageEvents($userId, $lang, $startAt);
            $timeline = $this->buildProgressTimeline($startAt, $startValue, $events, $hasActivity ? $composite : null);

            $stats[] = [
                'language'          => $lang,
                'start_value'       => $startValue,
                'current_value'     => round($current, 1),
                'change'            => $delta,
                'trend'             => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
                'lessons_completed' => $lessonsCompleted,
                'lessons_total'     => $lessonsTotal,
                'interviews'        => $interviewsCount,
                'code_reviews'      => $reviewsCount,
                'learning_score'    => $moduleScores['learning'],
                'interview_score'   => $moduleScores['interviews'],
                'review_score'      => $moduleScores['review'],
                'grade'             => $this->progressGradeLabel($current),
                'has_activity'      => $hasActivity,
                'kpi'               => $this->buildLanguageKpi(
                    $lessonRows,
                    $interviewRows,
                    $reviewRows,
                    $moduleScores,
                    $composite,
                    $hasActivity,
                ),
                'timeline'          => $timeline,
            ];
        }

        return collect($stats)->sortByDesc('current_value')->values()->all();
    }

    
    private function computeLanguageModuleScores(
        Collection $lessonRows,
        Collection $interviewRows,
        Collection $reviewRows,
    ): array {
        $completedLessons = $lessonRows->where('is_completed', true);

        $learning = 0.0;
        if ($completedLessons->isNotEmpty()) {
            $learning = (float) $completedLessons->avg(fn ($r) => (float) ($r->lesson_score ?? 50));
        }

        $interviews = 0.0;
        if ($interviewRows->isNotEmpty()) {
            $scores = $interviewRows
                ->where('status', 'completed')
                ->map(fn ($i) => $this->interviewScoreFromVerdict($i))
                ->filter(fn ($s) => $s !== null);

            $interviews = $scores->isNotEmpty() ? (float) $scores->avg() : 0.0;
        }

        $review = 0.0;
        if ($reviewRows->isNotEmpty()) {
            $review = (float) $reviewRows->avg(fn ($r) => (float) ($r->overall_score ?? 50));
        }

        return [
            'learning'   => round($learning, 1),
            'interviews' => round($interviews, 1),
            'review'     => round($review, 1),
        ];
    }

    private function computeLanguageProgressIndex(float $learning, float $interviews, float $review): float
    {
        return round($learning * 0.25 + $interviews * 0.35 + $review * 0.40, 1);
    }

    private function progressGradeLabel(float $index): string
    {
        if ($index >= 85) {
            return 'Senior';
        }
        if ($index >= 65) {
            return 'Middle';
        }
        if ($index >= 45) {
            return 'Junior';
        }
        if ($index >= 25) {
            return 'Beginner';
        }

        return 'Старт';
    }

    
    private function buildLanguageKpi(
        Collection $lessonRows,
        Collection $interviewRows,
        Collection $reviewRows,
        array $moduleScores,
        float $composite,
        bool $hasActivity,
    ): array {
        if (!$hasActivity) {
            return [
                'time_label'            => '0ч',
                'accuracy_pct'          => null,
                'code_quality_label'    => null,
                'market_readiness_pct'  => 0,
            ];
        }

        $completed = $lessonRows->where('is_completed', true);
        $minutes   = $completed->count() * 35
            + $interviewRows->where('status', 'completed')->count() * 25
            + $reviewRows->count() * 15;

        $firstTry = $completed->filter(fn ($r) => (int) ($r->hints_used ?? 0) === 0);
        $accuracy = $completed->isNotEmpty()
            ? (int) round($firstTry->count() / max(1, $completed->count()) * 100)
            : null;

        $qualityLabel = null;
        if ($moduleScores['review'] > 0) {
            $qualityLabel = round($moduleScores['review'] / 10, 1) . '/10';
        }

        $market = (int) round(
            min(100, max(0, $composite * 0.55 + $moduleScores['interviews'] * 0.30 + $moduleScores['review'] * 0.15)),
        );

        return [
            'time_label'           => $this->formatDurationLabel($minutes),
            'accuracy_pct'         => $accuracy,
            'code_quality_label'   => $qualityLabel,
            'market_readiness_pct' => $market,
        ];
    }

    private function formatDurationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . 'м';
        }

        $hours = intdiv($minutes, 60);
        $rest  = $minutes % 60;

        return $rest > 0 ? "{$hours}ч {$rest}м" : "{$hours}ч";
    }

    private function interviewScoreFromVerdict(HrInterview $interview): ?float
    {
        if (!empty($interview->verdict['conduct_termination'])
            || !empty($interview->verdict['terminated_for_conduct'])) {
            return null;
        }

        $stars = $interview->verdict['star_scores'] ?? [];
        if (is_array($stars)) {
            $vals = collect(['situation', 'task', 'action', 'result'])
                ->map(fn ($k) => isset($stars[$k]) && is_numeric($stars[$k]) ? (float) $stars[$k] : null)
                ->filter();
            if ($vals->isNotEmpty()) {
                return round($vals->avg() / 5 * 100, 1);
            }
        }

        $dec = strtolower((string) ($interview->verdict['decision'] ?? ''));
        if (in_array($dec, ['hire', 'strong_hire', 'yes'], true)) {
            return 85.0;
        }
        if (in_array($dec, ['no_hire', 'no', 'reject'], true)) {
            return 35.0;
        }

        return $interview->status === 'completed' ? 55.0 : null;
    }

    private function interviewStarAverage(HrInterview $interview): ?float
    {
        $stars = $interview->verdict['star_scores'] ?? [];
        if (!is_array($stars)) {
            return null;
        }
        $vals = collect(['situation', 'task', 'action', 'result'])
            ->map(fn ($k) => isset($stars[$k]) && is_numeric($stars[$k]) ? (float) $stars[$k] : null)
            ->filter();

        return $vals->isNotEmpty() ? round($vals->avg(), 1) : null;
    }

    private function interviewEventTitle(HrInterview $interview): string
    {
        $level = ucfirst(strtolower((string) ($interview->level ?: 'Junior')));
        $lang  = $this->prettyDirection($interview->direction);

        return trim("{$level} {$lang} Dev");
    }

    private function interviewEventResult(HrInterview $interview): string
    {
        $starAvg = $this->interviewStarAverage($interview);
        if ($starAvg !== null) {
            return $starAvg . ' / 5';
        }

        $dec = strtolower((string) ($interview->verdict['decision'] ?? ''));
        if (in_array($dec, ['hire', 'strong_hire', 'yes'], true)) {
            return 'Успешно';
        }
        if (in_array($dec, ['no_hire', 'no', 'reject'], true)) {
            return 'Отказ';
        }

        return 'Завершено';
    }

    private function startIndexForBaseline(?array $baseline): float
    {
        if (!$baseline) {
            return 10.0;
        }

        if ($baseline['source'] === 'test' && !empty($baseline['level'])) {
            return match (strtolower($baseline['level'])) {
                'beginner' => 25.0,
                'junior'   => 45.0,
                'middle', 'mid' => 65.0,
                'senior'   => 85.0,
                default    => 35.0,
            };
        }

        return 10.0;
    }

    
    private function collectLanguageEvents(int $userId, string $lang, Carbon $startAt): array
    {
        $events = [];

        UserProgressSubtopic::query()
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->where('completed_at', '>=', $startAt)
            ->with('course.direction')
            ->get()
            ->filter(fn ($r) => $this->courseLanguage($r->course) === $lang)
            ->each(function ($r) use (&$events) {
                $score = (float) ($r->lesson_score ?? 50);
                $title = $r->task_title ?? $r->course?->title ?? 'Урок';
                $events[] = [
                    'at'           => $r->completed_at ?? $r->updated_at,
                    'kind'         => 'lesson',
                    'module'       => 'Обучение',
                    'title'        => (string) $title,
                    'result'       => round($score) . ' / 100',
                    'module_score' => $score,
                ];
            });

        HrInterview::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->where('finished_at', '>=', $startAt)
            ->get()
            ->filter(fn ($i) => $this->prettyDirection($i->direction) === $lang)
            ->each(function ($i) use (&$events) {
                $score = $this->interviewScoreFromVerdict($i) ?? 55.0;
                $title = $this->interviewEventTitle($i);
                $events[] = [
                    'at'           => $i->finished_at ?? $i->created_at,
                    'kind'         => 'interview',
                    'module'       => 'AI-Собеседование',
                    'title'        => $title,
                    'result'       => $this->interviewEventResult($i),
                    'module_score' => $score,
                ];
            });

        CodeReview::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $startAt)
            ->get()
            ->filter(fn ($r) => $this->prettyDirection($r->detected_language) === $lang)
            ->each(function ($r) use (&$events) {
                $score = (float) ($r->overall_score ?? 50);
                $lang  = $this->prettyDirection($r->detected_language);
                $title = $lang !== '—' ? "Ревью {$lang}" : 'Code Review';
                $events[] = [
                    'at'           => $r->created_at,
                    'kind'         => 'review',
                    'module'       => 'Code Review',
                    'title'        => $title,
                    'result'       => round($score) . ' / 100',
                    'module_score' => $score,
                ];
            });

        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $events;
    }

    
    private function buildProgressTimeline(Carbon $startAt, float $startValue, array $events, ?float $finalComposite = null): array
    {
        $points = [[
            'date'    => $startAt->format('Y-m-d'),
            'label'   => $startAt->locale('ru')->isoFormat('D MMM'),
            'value'   => round($startValue, 1),
            'kind'    => 'start',
            'module'  => 'Старт',
            'title'   => 'Точка отсчёта',
            'result'  => round($startValue, 1) . ' индекс',
            'tooltip' => $startAt->locale('ru')->isoFormat('D MMMM') . ' — Старт: ' . round($startValue, 1),
        ]];

        $learningScores   = [];
        $interviewScores  = [];
        $reviewScores     = [];

        foreach ($events as $event) {
            $at = $event['at'] instanceof Carbon ? $event['at'] : Carbon::parse($event['at']);

            match ($event['kind']) {
                'lesson'    => $learningScores[] = $event['module_score'],
                'interview' => $interviewScores[] = $event['module_score'],
                'review'    => $reviewScores[] = $event['module_score'],
                default     => null,
            };

            $learningAvg  = $learningScores !== [] ? array_sum($learningScores) / count($learningScores) : 0.0;
            $interviewAvg = $interviewScores !== [] ? array_sum($interviewScores) / count($interviewScores) : 0.0;
            $reviewAvg    = $reviewScores !== [] ? array_sum($reviewScores) / count($reviewScores) : 0.0;

            $hasModuleData = $learningScores !== [] || $interviewScores !== [] || $reviewScores !== [];
            $value         = $hasModuleData
                ? $this->computeLanguageProgressIndex($learningAvg, $interviewAvg, $reviewAvg)
                : $startValue;

            $dateLabel = $at->locale('ru')->isoFormat('D MMMM');
            $tooltip   = "{$dateLabel} — {$event['module']} ({$event['title']}): {$event['result']}";

            $points[] = [
                'date'    => $at->format('Y-m-d'),
                'label'   => $at->locale('ru')->isoFormat('D MMM H:mm'),
                'value'   => round($value, 1),
                'kind'    => $event['kind'],
                'module'  => $event['module'],
                'title'   => $event['title'],
                'result'  => $event['result'],
                'tooltip' => $tooltip,
            ];
        }

        $points = collect($points)
            ->groupBy('date')
            ->map(fn (Collection $group) => $group->last())
            ->values()
            ->all();

        if ($finalComposite !== null && count($points) > 0) {
            $points[count($points) - 1]['value'] = round($finalComposite, 1);
        }

        $today = now()->format('Y-m-d');
        $last  = $points[count($points) - 1];

        if (($last['date'] ?? '') !== $today) {
            $points[] = [
                'date'    => $today,
                'label'   => now()->locale('ru')->isoFormat('D MMM'),
                'value'   => $last['value'],
                'kind'    => 'now',
                'module'  => 'Сейчас',
                'title'   => 'Текущий индекс',
                'result'  => round((float) $last['value'], 1) . ' индекс',
                'tooltip' => now()->locale('ru')->isoFormat('D MMMM') . ' — Текущий индекс: ' . round((float) $last['value'], 1),
            ];
        }

        return $points;
    }
}
