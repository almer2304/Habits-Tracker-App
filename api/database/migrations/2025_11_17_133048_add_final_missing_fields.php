<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ==================== HABITS TABLE ====================
        if (!Schema::hasColumn('habits', 'target_frequency')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->enum('target_frequency', ['daily', 'weekly', 'monthly'])->default('daily');
            });
        }

        if (!Schema::hasColumn('habits', 'target_count')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->integer('target_count')->default(1);
            });
        }

        if (!Schema::hasColumn('habits', 'color')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->string('color', 7)->default('#3B82F6');
            });
        }

        if (!Schema::hasColumn('habits', 'icon')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->string('icon', 50)->default('📝');
            });
        }

        if (!Schema::hasColumn('habits', 'is_active')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->boolean('is_active')->default(true);
            });
        }

        if (!Schema::hasColumn('habits', 'reminder_time')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->time('reminder_time')->nullable();
            });
        }

        if (!Schema::hasColumn('habits', 'start_date')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->date('start_date')->default(now()->toDateString());
            });
        }

        if (!Schema::hasColumn('habits', 'end_date')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->date('end_date')->nullable();
            });
        }

        if (!Schema::hasColumn('habits', 'category')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->string('category', 100)->nullable();
            });
        }

        if (!Schema::hasColumn('habits', 'privacy')) {
            Schema::table('habits', function (Blueprint $table) {
                $table->enum('privacy', ['public', 'private'])->default('private');
            });
        }

        // ==================== HABIT_LOG TABLE ====================
        if (!Schema::hasColumn('habit_log', 'completion_value')) {
            Schema::table('habit_log', function (Blueprint $table) {
                $table->decimal('completion_value', 5, 2)->default(0.00);
            });
        }

        if (!Schema::hasColumn('habit_log', 'time_completed')) {
            Schema::table('habit_log', function (Blueprint $table) {
                $table->time('time_completed')->nullable();
            });
        }

        if (!Schema::hasColumn('habit_log', 'mood')) {
            Schema::table('habit_log', function (Blueprint $table) {
                $table->enum('mood', ['terrible', 'bad', 'neutral', 'good', 'excellent'])->nullable();
            });
        }

        if (!Schema::hasColumn('habit_log', 'difficulty_rating')) {
            Schema::table('habit_log', function (Blueprint $table) {
                $table->integer('difficulty_rating')->default(1);
            });
        }

        // Tambah unique constraint (coba-catch untuk handle error)
        try {
            Schema::table('habit_log', function (Blueprint $table) {
                if (!Schema::hasIndex('habit_log', 'habit_log_habit_id_date_unique')) {
                    $table->unique(['habit_id', 'date']);
                }
            });
        } catch (\Exception $e) {
            // Skip jika sudah ada
        }
    }

    public function down()
    {
        // Optional rollback - comment jika tidak perlu
        /*
        Schema::table('habits', function (Blueprint $table) {
            $table->dropColumn([
                'target_frequency', 'target_count', 'color', 'icon', 'is_active',
                'reminder_time', 'start_date', 'end_date', 'category', 'privacy'
            ]);
        });

        Schema::table('habit_log', function (Blueprint $table) {
            $table->dropUnique(['habit_id', 'date']);
            $table->dropColumn([
                'completion_value', 'time_completed', 'mood', 'difficulty_rating'
            ]);
        });
        */
    }
};