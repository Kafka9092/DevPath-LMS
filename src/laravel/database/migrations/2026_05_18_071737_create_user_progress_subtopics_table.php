<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('user_progress_subtopics', function (Blueprint $table) {
          $table->id();
    
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('subtopic_id')->constrained()->onDelete('cascade');
            $table->enum('delivery_mode', ['full', 'practice_only'])->default('full');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'course_id', 'subtopic_id']);
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('user_progress_subtopics');
    }
};
