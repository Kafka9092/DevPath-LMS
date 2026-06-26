<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Competence;

class CompetenceSeeder extends Seeder
{
    public function run()
    {
        $jsonPath = database_path('data/competencies.json');

        if (!file_exists($jsonPath)) {
            $this->command->error('Файл не найден: ' . $jsonPath);
            return;
        }

        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Ошибка JSON: ' . json_last_error_msg());
            return;
        }

        $competencies = $data['competencies'] ?? [];

        foreach ($competencies as $item) {
            Competence::updateOrCreate(
                ['name' => $item['name']],
                [
                    'category' => $item['category'],
                    'beginner_level' => $item['beginner_level'] ?? false,
                    'junior_level' => $item['junior_level'],
                    'middle_level' => $item['middle_level'],
                    'senior_level' => $item['senior_level'],
                ]
            );
        }

        $this->command->info('Компетенции успешно загружены. Всего: ' . count($competencies));
    }
}
