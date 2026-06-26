<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_users', function (Blueprint $table) {
            $table->string('status')->default('active')->after('course_id');
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
            $table->timestamp('started_at')->nullable()->after('progress');
            
            
            if (!Schema::hasTable('course_users') || !Schema::hasIndex('course_users', ['user_id', 'course_id'])) {
                $table->unique(['user_id', 'course_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_users', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'course_id']);
            $table->dropColumn(['status', 'progress', 'started_at']);
        });
    }
};
