<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('competences', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->nullable();
            $table->boolean('beginner_level')->default(false);
            $table->boolean('junior_level')->default(false);
            $table->boolean('middle_level')->default(false);
            $table->boolean('senior_level')->default(false);
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('competences');
    }
};
