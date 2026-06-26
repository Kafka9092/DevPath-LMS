<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('detected_language', 32);
            $table->longText('code');
            $table->string('sonar_project_key', 64)->nullable();
            $table->json('sonar_metrics')->nullable();
            $table->json('sonar_issues')->nullable();
            $table->json('ai_evaluation')->nullable();
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_reviews');
    }
};
