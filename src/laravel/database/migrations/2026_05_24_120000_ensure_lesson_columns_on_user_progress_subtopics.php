<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_progress_subtopics')) {
            return;
        }

        Schema::table('user_progress_subtopics', function (Blueprint $table) {
            if (!Schema::hasColumn('user_progress_subtopics', 'generated_theory')) {
                $table->text('generated_theory')->nullable();
            }
            if (!Schema::hasColumn('user_progress_subtopics', 'task_title')) {
                $table->string('task_title')->nullable();
            }
            if (!Schema::hasColumn('user_progress_subtopics', 'task_description')) {
                $table->text('task_description')->nullable();
            }
            if (!Schema::hasColumn('user_progress_subtopics', 'submitted_code')) {
                $table->longText('submitted_code')->nullable();
            }
            if (!Schema::hasColumn('user_progress_subtopics', 'lesson_score')) {
                $table->unsignedTinyInteger('lesson_score')->nullable();
            }
            if (!Schema::hasColumn('user_progress_subtopics', 'lesson_feedback')) {
                $table->text('lesson_feedback')->nullable();
            }
            if (!Schema::hasColumn('user_progress_subtopics', 'lesson_review')) {
                $table->jsonb('lesson_review')->nullable();
            }
            if (!Schema::hasColumn('user_progress_subtopics', 'hints_used')) {
                $table->unsignedSmallInteger('hints_used')->default(0);
            }
            if (!Schema::hasColumn('user_progress_subtopics', 'theory_part_index')) {
                $table->unsignedTinyInteger('theory_part_index')->default(0);
            }
            if (!Schema::hasColumn('user_progress_subtopics', 'theory_complete')) {
                $table->boolean('theory_complete')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_progress_subtopics')) {
            return;
        }

        Schema::table('user_progress_subtopics', function (Blueprint $table) {
            foreach (['theory_part_index', 'theory_complete'] as $col) {
                if (Schema::hasColumn('user_progress_subtopics', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
