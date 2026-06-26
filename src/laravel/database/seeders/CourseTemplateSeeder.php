<?php

namespace Database\Seeders;

use App\Models\CourseTemplate;
use App\Models\Level;
use App\Service\CourseTemplateStructureService;
use Illuminate\Database\Seeder;

class CourseTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $beginnerLevel = Level::where('name', 'beginner')->first();
        $juniorLevel = Level::where('name', 'junior')->first();
        $middleLevel = Level::where('name', 'middle')->first();
        $seniorLevel = Level::where('name', 'senior')->first();

        if (! $juniorLevel || ! $middleLevel || ! $seniorLevel) {
            $this->command->error('Уровни beginner/junior/middle/senior не найдены в БД. Сначала создайте их.');
            return;
        }

        $jsonPath = database_path('data/course_templates.json');

        if (! file_exists($jsonPath)) {
            $this->command->error('Файл не найден: ' . $jsonPath);
            return;
        }

        $jsonContent = file_get_contents($jsonPath);
        $templates = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Ошибка JSON: ' . json_last_error_msg());
            return;
        }

        $structureService = app(CourseTemplateStructureService::class);

        foreach ([
            'beginner' => $beginnerLevel,
            'junior' => $juniorLevel,
            'middle' => $middleLevel,
            'senior' => $seniorLevel,
        ] as $levelName => $level) {
            if (! isset($templates[$levelName]) || ! $level) {
                continue;
            }

            $structure = $structureService->normalize($templates[$levelName]);

            CourseTemplate::updateOrCreate(
                ['level_id' => $level->id],
                [
                    'structure' => $structure,
                    'structure_hash' => $structureService->hash($structure),
                ],
            );

            $this->command->info("Шаблон для уровня {$levelName} сохранен");
        }

        $this->command->info('Шаблоны курсов успешно созданы');
    }
}
