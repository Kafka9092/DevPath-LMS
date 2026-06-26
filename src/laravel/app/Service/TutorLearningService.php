<?php

namespace App\Service;

use App\Models\Course;
use App\Models\CourseTemplate;
use App\Models\Direction;
use App\Models\Level;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Создание персонального курса из шаблона + результатов CAT-теста. */
class TutorLearningService
{
    public function __construct(
        protected UserLearningProfileService $profileService,
        protected CatAssessmentService $catAssessments,
        protected CourseTemplateStructureService $templateStructureService,
    ) {}

    private const DIRECTION_LABELS = [
        'php'        => 'PHP',
        'python'     => 'Python',
        'javascript' => 'JavaScript',
        'typescript' => 'TypeScript',
        'java'       => 'Java',
        'c++'        => 'C++',
        'c#'         => 'C#',
        'go'         => 'Go',
        'ruby'       => 'Ruby',
    ];

    /**
     * Берём course_template по уровню, копируем структуру в courses/modules/...
     * Профиль пользователя сохраняем отдельно.
     */
    public function generateLearningPlan(
        string $directionName,
        string $levelName,
        int    $userId,
        array  $competenceScores    = [],
        array  $learningPreferences = [],
    ): Course {
        return DB::transaction(function () use (
            $directionName, $levelName, $userId, $competenceScores, $learningPreferences
        ) {
            $directionLabel = $this->normalizeDirectionName($directionName);
            $direction      = Direction::firstOrCreate(['name' => $directionLabel]);
            $level          = Level::firstOrCreate(['name' => strtolower($levelName)]);

            $template = CourseTemplate::where('level_id', $level->id)->first();
            if (!$template) {
                throw new \RuntimeException(
                    "Шаблон курса для уровня {$level->name} не найден. Запустите CourseTemplateSeeder."
                );
            }

            $templateHash = $template->structure_hash
                ?? $this->templateStructureService->hash($template->structure ?? []);

            $course = Course::query()
                ->where('direction_id', $direction->id)
                ->where('level_id', $level->id)
                ->where('template_structure_hash', $templateHash)
                ->first();

            if (! $course) {
                try {
                    $course = $this->createCourseFromTemplate($template, $direction, $level, $templateHash);
                } catch (QueryException $exception) {
                    if (! $this->isDuplicateCourseConstraintViolation($exception)) {
                        throw $exception;
                    }

                    $course = Course::query()
                        ->where('direction_id', $direction->id)
                        ->where('level_id', $level->id)
                        ->where('template_structure_hash', $templateHash)
                        ->first();
                }

                if (! $course) {
                    throw new \RuntimeException('Не удалось создать или найти курс для текущей версии шаблона.');
                }
            }

            if (!$course->users()->where('user_id', $userId)->exists()) {
                $course->users()->attach($userId, [
                    'status'     => 'active',
                    'progress'   => 0,
                    'started_at' => now(),
                ]);
            }

            if (!empty($learningPreferences)) {
                $this->profileService->saveFromOnboarding($userId, $course->id, [
                    'style'   => $learningPreferences['style'] ?? UserLearningProfileService::PREFERENCE_BALANCED,
                    'domain'  => $learningPreferences['domain'] ?? null,
                    'persona' => $learningPreferences['persona'] ?? UserLearningProfileService::PERSONA_COLLEAGUE,
                    'goal'    => $learningPreferences['goal'] ?? null,
                ]);
            }

            $assessmentUuid = session('cat_assessment_uuid');
            if ($assessmentUuid) {
                $this->catAssessments->linkToCourse($assessmentUuid, $course->id);
            }

            session([
                'course_competence_scores' => $competenceScores,
                'active_course_id'         => $course->id,
            ]);
            session()->forget('cat_assessment_uuid');

            Log::info('Course assigned to user', [
                'course_id' => $course->id,
                'user_id'   => $userId,
                'direction' => $directionLabel,
                'level'     => $level->name,
            ]);

            return $course->load(['direction', 'level', 'modules.themes.subtopics']);
        });
    }

    private function createCourseFromTemplate(
        CourseTemplate $template,
        Direction $direction,
        Level $level,
        string $templateHash,
    ): Course {
        $structure = $this->templateStructureService->normalize($template->structure ?? []);
        $titleTpl  = $structure['title'] ?? 'Курс {direction}';
        $descTpl   = $structure['description'] ?? 'Курс по {direction}, уровень {level}';

        $course = Course::create([
            'title'                   => $this->applyPlaceholders($titleTpl, $direction->name, $level->name),
            'description'             => $this->applyPlaceholders($descTpl, $direction->name, $level->name),
            'status'                  => 'active',
            'direction_id'            => $direction->id,
            'level_id'                => $level->id,
            'course_template_id'      => $template->id,
            'template_structure_hash' => $templateHash,
        ]);

        $themeOrder = 0;
        foreach ($structure['modules'] ?? [] as $moduleData) {
            $module = $course->modules()->create([
                'title'         => $this->applyPlaceholders($moduleData['title'] ?? 'Модуль', $direction->name, $level->name),
                'module_number' => $moduleData['module_number'] ?? 1,
                'description'   => isset($moduleData['description'])
                    ? $this->applyPlaceholders($moduleData['description'], $direction->name, $level->name)
                    : null,
            ]);

            foreach ($moduleData['themes'] ?? [] as $themeData) {
                $themeOrder++;
                $theme = $module->themes()->create([
                    'title' => $this->applyPlaceholders($themeData['title'] ?? 'Тема', $direction->name, $level->name),
                    'order' => $themeData['order'] ?? $themeOrder,
                ]);

                $subtopicOrder = 0;
                foreach ($themeData['subtopics'] ?? [] as $subtopicData) {
                    $subtopicOrder++;
                    $theme->subtopics()->create([
                        'title'  => $this->applyPlaceholders($subtopicData['title'] ?? 'Подтема', $direction->name, $level->name),
                        'task'   => isset($subtopicData['task'])
                            ? $this->applyPlaceholders($subtopicData['task'], $direction->name, $level->name)
                            : null,
                        'theory' => isset($subtopicData['theory'])
                            ? $this->applyPlaceholders($subtopicData['theory'], $direction->name, $level->name)
                            : null,
                        'order'  => $subtopicData['order'] ?? $subtopicOrder,
                    ]);
                }
            }
        }

        Log::info('Course created from template', [
            'course_id'      => $course->id,
            'template_id'    => $template->id,
            'template_hash'  => $templateHash,
        ]);

        return $course;
    }

    private function applyPlaceholders(string $text, string $direction, string $level): string
    {
        return str_replace(
            ['{direction}', '{level}'],
            [$direction, $level],
            $text
        );
    }

    private function normalizeDirectionName(string $name): string
    {
        $key = strtolower(trim($name));

        return self::DIRECTION_LABELS[$key] ?? ucfirst($key);
    }

    private function isDuplicateCourseConstraintViolation(QueryException $exception): bool
    {
        $errorCode = (string) ($exception->errorInfo[0] ?? '');
        $message = strtolower($exception->getMessage());

        return $errorCode === '23505'
            || str_contains($message, 'courses_direction_level_template_hash_unique')
            || str_contains($message, 'duplicate key value');
    }
}
