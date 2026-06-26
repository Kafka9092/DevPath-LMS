<?php

namespace Tests\Concerns;

use App\Models\Course;
use App\Models\Direction;
use App\Models\Level;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\Theme;
use App\Models\User;
use App\Models\UserProgressSubtopic;

trait BuildsLessonFixtures
{
    /** @return array{user: User, course: Course, subtopic: Subtopic, progress: UserProgressSubtopic} */
    protected function createLessonWorkspace(User $user, bool $theoryComplete = false): array
    {
        $direction = Direction::query()->create(['name' => 'PHP']);
        $level = Level::query()->create(['name' => 'junior']);
        $course = Course::query()->create([
            'title'        => 'Тестовый курс PHP',
            'status'       => 'active',
            'direction_id' => $direction->id,
            'level_id'     => $level->id,
        ]);
        $course->users()->attach($user->id, ['status' => 'active', 'progress' => 0]);

        $module = Module::query()->create([
            'module_number' => 1,
            'title'         => 'Основы',
            'description'   => 'Базовый модуль',
            'course_id'     => $course->id,
        ]);
        $theme = Theme::query()->create([
            'title'       => 'Массивы',
            'description' => 'Работа с массивами',
            'order'       => 1,
            'module_id'   => $module->id,
        ]);
        $subtopic = Subtopic::query()->create([
            'theme_id' => $theme->id,
            'title'    => 'Массивы в PHP',
            'task'     => 'Реализуйте функцию фильтрации массива.',
            'order'    => 1,
        ]);

        $taskDescription = str_repeat(
            'Напишите функцию filterArray, которая принимает массив и callable, возвращает отфильтрованный массив. ',
            4,
        );

        $generatedTheory = json_encode([
            'pregenerated' => true,
            'slides'       => [[
                'content'     => 'Массивы в PHP — упорядоченные коллекции элементов.',
                'slide_title' => 'Введение',
                'has_more'    => true,
            ]],
            'chunks' => ['Массивы в PHP — упорядоченные коллекции элементов.'],
        ], JSON_UNESCAPED_UNICODE);

        $progress = UserProgressSubtopic::query()->create([
            'user_id'         => $user->id,
            'course_id'       => $course->id,
            'subtopic_id'     => $subtopic->id,
            'delivery_mode'     => 'full',
            'generated_theory'  => $generatedTheory,
            'task_title'        => 'Фильтрация массива',
            'task_description'  => $taskDescription,
            'theory_part_index' => 1,
            'theory_complete'   => $theoryComplete,
            'is_completed'      => false,
        ]);

        return compact('user', 'course', 'subtopic', 'progress');
    }
}
