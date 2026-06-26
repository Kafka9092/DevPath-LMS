<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('subtopics', function (Blueprint $table) {
            $table->id();
            $table->string('title'); 
            $table->text('theory')->nullable(); 
            $table->string('task')->nullable();
            $table->integer('order')->default(0); 
            $table->foreignId('theme_id')->constrained()->onDelete('cascade'); 
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('subtopics');
    }
};
