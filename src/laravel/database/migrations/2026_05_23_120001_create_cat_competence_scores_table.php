<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_competence_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cat_assessment_id')->constrained('cat_assessments')->cascadeOnDelete();
            $table->foreignId('competence_id')->constrained('competences')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->boolean('mastered')->default(false);
            $table->string('tested_at_level', 32)->nullable();
            $table->timestamps();

            
            $table->unique(['cat_assessment_id', 'competence_id'], 'cat_comp_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_competence_scores');
    }
};
