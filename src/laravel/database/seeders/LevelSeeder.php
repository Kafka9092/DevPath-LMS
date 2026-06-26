<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['id' => 1, 'name' => 'beginner', 'created_at' => '2026-05-12 23:18:53', 'updated_at' => '2026-05-12 23:18:53'],
            ['id' => 2, 'name' => 'junior', 'created_at' => '2026-05-12 23:18:30', 'updated_at' => '2026-05-12 23:18:30'],
            ['id' => 3, 'name' => 'middle', 'created_at' => '2026-05-12 23:18:38', 'updated_at' => '2026-05-12 23:18:38'],
            ['id' => 4, 'name' => 'senior', 'created_at' => '2026-05-12 23:18:45', 'updated_at' => '2026-05-12 23:18:45'],
        ];

        foreach ($levels as $level) {
            DB::table('levels')->updateOrInsert(
                ['id' => $level['id']],
                [
                    'name' => $level['name'],
                    'created_at' => $level['created_at'],
                    'updated_at' => $level['updated_at'],
                ]
            );
        }

        if (config('database.default') === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('levels', 'id'), coalesce(max(id), 0) + 1, false) FROM levels;");
        }
    }
}
