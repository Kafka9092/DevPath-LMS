<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_interviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('conduct_warnings')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('hr_interviews', function (Blueprint $table) {
            $table->dropColumn('conduct_warnings');
        });
    }
};
