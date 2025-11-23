<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('habit_log', function (Blueprint $table) {
            $table->integer('xp_earned')->default(0);
            $table->integer('streak_bonus')->default(0);
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habit_log', function (Blueprint $table) {
            $table->dropColumn(['xp_earned', 'streak_bonus', 'notes']);
        });
    }
};
