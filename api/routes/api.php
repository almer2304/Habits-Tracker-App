<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\HabitLogController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // ==================== AUTHENTICATION ====================
    Route::get('/user', function (Request $request) {
        return $request->user()->load('badges');
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    
    // ==================== HABITS MANAGEMENT ====================
    Route::apiResource('/habits', HabitController::class);
    
    // Habit Statistics & Analytics
    Route::get('/habits/{id}/stats', [HabitController::class, 'getHabitStats']);
    Route::get('/habits/stats/dashboard', [HabitController::class, 'dashboardStats']);
    Route::get('/analytics/success-rates', [HabitController::class, 'successRates']);

    // ==================== HABIT LOGS & TRACKING ====================
    
    // Habit Logs Views & Reports
    Route::get('/habit-logs/calendar', [HabitLogController::class, 'calendarData']);
    Route::get('/habit-logs/stats/overview', [HabitLogController::class, 'statsOverview']);
    Route::get('/habit-logs/streaks', [HabitLogController::class, 'getStreaks']);
    
    // Reports & Analytics
    Route::get('/reports/weekly', [HabitLogController::class, 'weeklyReport']);
    Route::get('/reports/monthly', [HabitLogController::class, 'monthlyReport']);
    
    Route::apiResource('/habit-logs', HabitLogController::class);
    
    
    // ==================== GAMIFICATION SYSTEM ====================
    Route::get('/badges', [BadgeController::class, 'index']);
    Route::get('/badges/{id}', [BadgeController::class, 'show']);
    Route::get('/user/badges', [BadgeController::class, 'userBadges']);
    Route::get('/badges-with-progress', [BadgeController::class, 'getAllBadgesWithUserProgress']);
    Route::get('/badges/{id}/progress', [BadgeController::class, 'getBadgeProgress']); // BARU
    Route::post('/badges/check', [BadgeController::class, 'checkBadges']); // BARU
    Route::post('/badges/{id}/claim', [BadgeController::class, 'claimBadge']);
    
    
    // ==================== USER PROGRESS & SOCIAL ====================
    Route::get('/user/progress', [UserController::class, 'getUserProgress']);
    Route::get('/user/leaderboard', [UserController::class, 'getLeaderboard']);
    Route::get('/user/achievements', [UserController::class, 'getAchievements']);
    
    // ==================== DASHBOARD & OVERVIEW ====================
    Route::get('/dashboard', [HabitController::class, 'dashboard']);
    Route::get('/dashboard/quick-stats', [HabitController::class, 'quickStats']);
});

// Fallback route
Route::fallback(function () {
    return response()->json([
        'message' => 'API endpoint not found'
    ], 404);
});