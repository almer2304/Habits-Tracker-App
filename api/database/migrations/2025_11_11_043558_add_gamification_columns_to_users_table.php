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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('level')->default(1);
            $table->integer('current_xp')->default(0);
            $table->integer('total_xp')->default(0);
            $table->integer('coins')->default(0);
            $table->integer('streak_count')->default(0);
            $table->date('last_activity_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['level', 'current_xp', 'total_xp', 'coins', 'streak_count', 'last_activity_date']);
        });
    }
};
