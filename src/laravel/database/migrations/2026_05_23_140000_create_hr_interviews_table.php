<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 50);
            $table->string('level', 20);
            $table->string('status', 20)->default('active');
            $table->jsonb('verdict')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'hr_interviews_user_status_idx');
        });

        Schema::create('hr_interview_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('hr_interviews')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->boolean('has_code_task')->default(false);
            $table->text('code_snippet')->nullable();
            $table->timestamps();

            $table->index(['interview_id', 'created_at'], 'hr_interview_msgs_interview_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_interview_messages');
        Schema::dropIfExists('hr_interviews');
    }
};
