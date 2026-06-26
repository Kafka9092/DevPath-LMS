<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cat_assessments')) {
            return;
        }

        $columns = array_filter(
            ['sonar_metrics', 'sonar_issues'],
            fn (string $column) => Schema::hasColumn('cat_assessments', $column),
        );

        if ($columns === []) {
            return;
        }

        Schema::table('cat_assessments', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('cat_assessments', function (Blueprint $table) {
            $table->jsonb('sonar_metrics')->nullable()->after('practical_feedback');
            $table->jsonb('sonar_issues')->nullable()->after('sonar_metrics');
        });
    }
};
