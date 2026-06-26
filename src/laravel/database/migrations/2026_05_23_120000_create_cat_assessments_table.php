<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('direction', 64);
            $table->string('overall_level', 32);
            $table->string('stop_reason', 64)->nullable();
            $table->unsignedSmallInteger('questions_answered')->default(0);
            $table->jsonb('weak_topics')->nullable();
            $table->jsonb('strong_topics')->nullable();
            $table->text('recommendation')->nullable();
            $table->boolean('practical_skipped')->default(false);
            $table->jsonb('practical_feedback')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            
            $table->index(['user_id', 'course_id'], 'cat_assess_user_course_idx');
            $table->index(['user_id', 'direction'], 'cat_assess_user_dir_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_assessments');
    }
};
