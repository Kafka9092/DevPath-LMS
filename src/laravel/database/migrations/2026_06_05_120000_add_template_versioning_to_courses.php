<?php

use App\Models\Course;
use App\Models\CourseTemplate;
use App\Service\CourseTemplateStructureService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_templates', function (Blueprint $table) {
            $table->string('structure_hash', 64)->nullable()->after('structure');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('course_template_id')
                ->nullable()
                ->after('level_id')
                ->constrained('course_templates')
                ->nullOnDelete();
            $table->string('template_structure_hash', 64)->nullable()->after('course_template_id');
            $table->unique(
                ['direction_id', 'level_id', 'template_structure_hash'],
                'courses_direction_level_template_hash_unique',
            );
        });

        $structureService = app(CourseTemplateStructureService::class);

        CourseTemplate::query()->each(function (CourseTemplate $template) use ($structureService): void {
            if (! is_array($template->structure)) {
                return;
            }

            $template->update([
                'structure_hash' => $structureService->hash($template->structure),
            ]);
        });

        Course::query()->each(function (Course $course) use ($structureService): void {
            $template = CourseTemplate::query()
                ->where('level_id', $course->level_id)
                ->first();

            $course->update([
                'course_template_id' => $template?->id,
                'template_structure_hash' => $structureService->legacyCourseHash($course->id),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique('courses_direction_level_template_hash_unique');
            $table->dropConstrainedForeignId('course_template_id');
            $table->dropColumn('template_structure_hash');
        });

        Schema::table('course_templates', function (Blueprint $table) {
            $table->dropColumn('structure_hash');
        });
    }
};
