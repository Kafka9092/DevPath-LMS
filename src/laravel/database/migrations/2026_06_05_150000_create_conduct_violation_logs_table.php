<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conduct_violation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32);
            $table->string('category', 32);
            $table->string('action', 32);
            $table->unsignedTinyInteger('warning_number')->default(1);
            $table->string('message_excerpt', 500);
            $table->string('classifier_reason', 500)->nullable();
            $table->decimal('classifier_confidence', 4, 3)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['source', 'category', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conduct_violation_logs');
    }
};
