<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_progress_subtopics', function (Blueprint $table) {
            $table->text('generated_theory')->nullable()->after('delivery_mode');
            $table->string('task_title')->nullable();
            $table->text('task_description')->nullable();
            $table->longText('submitted_code')->nullable();
            $table->unsignedTinyInteger('lesson_score')->nullable();
            $table->text('lesson_feedback')->nullable();
            $table->jsonb('lesson_review')->nullable();
            $table->unsignedSmallInteger('hints_used')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('user_progress_subtopics', function (Blueprint $table) {
            $table->dropColumn([
                'generated_theory',
                'task_title',
                'task_description',
                'submitted_code',
                'lesson_score',
                'lesson_feedback',
                'lesson_review',
                'hints_used',
            ]);
        });
    }
};
