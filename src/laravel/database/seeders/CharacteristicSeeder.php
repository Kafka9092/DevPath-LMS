<?php

namespace Database\Seeders;

use App\Models\Characteristic;
use Illuminate\Database\Seeder;

class CharacteristicSeeder extends Seeder
{
    
    public function run(): void
    {
        $items = [
            ['name' => 'correctness',       'label' => 'Корректность решения'],
            ['name' => 'readability',       'label' => 'Читаемость кода'],
            ['name' => 'code_structure',   'label' => 'Структура и организация'],
            ['name' => 'problem_solving',  'label' => 'Логика решения'],
            ['name' => 'best_practices',   'label' => 'Следование практикам'],
        ];

        foreach ($items as $item) {
            Characteristic::updateOrCreate(
                ['name' => $item['name']],
                ['label' => $item['label']],
            );
        }
    }
}
