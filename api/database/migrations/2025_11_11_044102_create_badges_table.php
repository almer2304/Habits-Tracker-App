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
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('icon'); // emoji atau URL icon
            $table->enum('category', ['streak', 'consistency', 'variety', 'mastery', 'special']);
            $table->string('requirement_type'); // total_completions, streak_days, level, etc
            $table->integer('requirement_target');
            $table->enum('habit_type', ['good', 'bad', 'any'])->default('any');
            $table->integer('reward_xp');
            $table->integer('reward_coins');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
