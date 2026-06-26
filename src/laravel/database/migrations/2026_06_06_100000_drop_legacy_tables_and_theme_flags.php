<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['user_learning_events', 'progress_users', 'test_results', 'tests'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('themes')) {
            $columns = array_filter(
                ['unlocked', 'completed'],
                fn (string $column) => Schema::hasColumn('themes', $column),
            );

            if ($columns !== []) {
                Schema::table('themes', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_learning_events')) {
            Schema::create('user_learning_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subtopic_id')->nullable()->constrained()->nullOnDelete();
                $table->string('event_type', 64);
                $table->jsonb('metadata')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'course_id', 'event_type', 'created_at'], 'ule_user_course_event_created_idx');
            });
        }

        if (!Schema::hasTable('progress_users')) {
            Schema::create('progress_users', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tests')) {
            Schema::create('tests', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('test_results')) {
            Schema::create('test_results', function (Blueprint $table) {
                $table->id();
                $table->string('assessment');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('characteristic_id')->constrained()->onDelete('cascade');
                $table->foreignId('competence_id')->constrained()->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('themes')) {
            Schema::table('themes', function (Blueprint $table) {
                if (!Schema::hasColumn('themes', 'unlocked')) {
                    $table->boolean('unlocked')->default(false)->after('order');
                }
                if (!Schema::hasColumn('themes', 'completed')) {
                    $table->boolean('completed')->default(false)->after('unlocked');
                }
            });
        }
    }
};
