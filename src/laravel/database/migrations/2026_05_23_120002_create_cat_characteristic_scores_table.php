<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_characteristic_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cat_assessment_id')->constrained('cat_assessments')->cascadeOnDelete();
            $table->foreignId('characteristic_id')->constrained('characteristics')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('source', 32)->default('practical_task');
            $table->text('feedback')->nullable();
            $table->timestamps();

            
            $table->unique(['cat_assessment_id', 'characteristic_id', 'source'], 'cat_char_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_characteristic_scores');
    }
};
