<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    
    public function run(): void
    {
        

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            \Database\Seeders\RoleAndPermissionSeeder::class,
            \Database\Seeders\LevelSeeder::class,
            \Database\Seeders\CharacteristicSeeder::class,
            \Database\Seeders\CourseTemplateSeeder::class,
            \Database\Seeders\CompetenceSeeder::class,
        ]);
    }
}
