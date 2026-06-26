<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('course_templates', function (Blueprint $table) {
            $table->id();
            $table->jsonb('structure');
            $table->foreignId('level_id')->constrained('levels')->unique();
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('course_templates');
    }
};
