<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DirectionSeeder extends Seeder
{
    public function run(): void
    {
        $directions = [
            ['id' => 1, 'name' => 'php', 'created_at' => '2026-05-10 23:39:37', 'updated_at' => '2026-05-10 23:39:37'],
            ['id' => 2, 'name' => 'python', 'created_at' => '2026-05-10 23:39:59', 'updated_at' => '2026-05-10 23:40:10'],
            ['id' => 3, 'name' => 'javascript', 'created_at' => '2026-05-15 15:08:58', 'updated_at' => '2026-05-15 15:08:58'],
            ['id' => 4, 'name' => 'typescript', 'created_at' => '2026-05-15 15:09:11', 'updated_at' => '2026-05-15 15:09:11'],
            ['id' => 5, 'name' => 'java', 'created_at' => '2026-05-15 15:09:18', 'updated_at' => '2026-05-15 15:09:18'],
            ['id' => 6, 'name' => 'go', 'created_at' => '2026-05-15 15:09:31', 'updated_at' => '2026-05-15 15:09:31'],
            ['id' => 7, 'name' => 'ruby', 'created_at' => '2026-05-15 15:09:42', 'updated_at' => '2026-05-15 15:09:42'],
            ['id' => 8, 'name' => 'csharp', 'created_at' => '2026-05-15 15:09:57', 'updated_at' => '2026-05-15 15:09:57'],
            ['id' => 9, 'name' => 'cpp', 'created_at' => '2026-05-15 15:10:06', 'updated_at' => '2026-05-15 15:10:06'],
        ];


        foreach ($directions as $direction) {
            DB::table('directions')->updateOrInsert(
                ['id' => $direction['id']],
                [
                    'name' => $direction['name'],
                    'created_at' => $direction['created_at'],
                    'updated_at' => $direction['updated_at'],
                ]
            );
        }

        if (config('database.default') === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('directions', 'id'), coalesce(max(id), 0) + 1, false) FROM directions;");
        }
    }
}
