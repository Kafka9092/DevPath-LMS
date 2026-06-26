<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Constraint\Constraint;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(); 
            $table->text('description')->nullable(); 
            $table->string('status')->default('active'); 
            $table->foreignId('direction_id')->constrained('directions');
            $table->foreignId('level_id')->constrained('levels');
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
