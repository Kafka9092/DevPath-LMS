<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_learning_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->enum('learning_preference', ['practice_heavy', 'balanced', 'theory_heavy'])->default('balanced');
            $table->string('domain_interest')->nullable();
            $table->enum('mentor_persona', ['strict_lead', 'colleague', 'soft_mentor'])->default('colleague');
            $table->enum('career_goal', ['senior_interview', 'mid_level_confidence', 'startup_architecture'])->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_learning_profiles');
    }
};
