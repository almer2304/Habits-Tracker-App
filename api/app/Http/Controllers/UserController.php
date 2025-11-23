<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user->update($request->only(['name', 'email']));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    public function getUserProgress(Request $request)
    {
        $user = $request->user()->load('badges');
        
        // Calculate additional stats
        $totalHabits = $user->habits()->count();
        $totalCompletions = $user->habitLogs()->where('status', 'completed')->count();
        
        return response()->json([
            'user' => $user,
            'stats' => [
                'total_habits' => $totalHabits,
                'total_completions' => $totalCompletions,
                'badges_count' => $user->badges->count(),
            ]
        ]);
    }

    public function getLeaderboard(Request $request)
    {
        $users = User::withCount('badges')
            ->orderBy('level', 'desc')
            ->orderBy('current_xp', 'desc')
            ->orderBy('badges_count', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'level', 'current_xp', 'streak_count']);

        return response()->json([
            'leaderboard' => $users
        ]);
    }

    public function getAchievements(Request $request)
    {
        $user = $request->user();
        
        // You can add more achievement logic here
        return response()->json([
            'achievements' => [
                'total_xp' => $user->total_xp,
                'current_level' => $user->level,
                'current_streak' => $user->streak_count,
                'total_badges' => $user->badges->count(),
            ]
        ]);
    }
}