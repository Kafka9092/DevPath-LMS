<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('title'); 
            $table->text('description')->nullable(); 
            $table->integer('order')->default(0);
            $table->foreignId('module_id')->constrained()->onDelete('cascade'); 
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
