<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_learning_profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('struggle_score')->default(0)->after('career_goal');
            $table->unsignedSmallInteger('success_streak')->default(0)->after('struggle_score');
            $table->timestamp('last_help_offered_at')->nullable()->after('success_streak');
        });
    }

    public function down(): void
    {
        Schema::table('user_learning_profiles', function (Blueprint $table) {
            $table->dropColumn(['struggle_score', 'success_streak', 'last_help_offered_at']);
        });
    }
};
