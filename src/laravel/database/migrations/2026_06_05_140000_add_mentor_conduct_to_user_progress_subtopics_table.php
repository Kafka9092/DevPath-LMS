<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_progress_subtopics', function (Blueprint $table) {
            if (! Schema::hasColumn('user_progress_subtopics', 'mentor_conduct_warnings')) {
                $table->unsignedTinyInteger('mentor_conduct_warnings')->default(0)->after('hints_used');
            }
            if (! Schema::hasColumn('user_progress_subtopics', 'mentor_chat_blocked')) {
                $table->boolean('mentor_chat_blocked')->default(false)->after('mentor_conduct_warnings');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_progress_subtopics', function (Blueprint $table) {
            if (Schema::hasColumn('user_progress_subtopics', 'mentor_chat_blocked')) {
                $table->dropColumn('mentor_chat_blocked');
            }
            if (Schema::hasColumn('user_progress_subtopics', 'mentor_conduct_warnings')) {
                $table->dropColumn('mentor_conduct_warnings');
            }
        });
    }
};
