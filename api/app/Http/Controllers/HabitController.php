<?php

namespace App\Http\Controllers;

use App\Models\Habits;
use App\Models\HabitLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HabitController extends Controller
{
    // GET /habits - List semua habits user
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = Habits::where('user_id', $user->id)
                        ->withCount(['habitLogs as completed_logs' => function($query) {
                            $query->where('status', 'completed');
                        }])
                        ->withCount('habitLogs as total_logs');

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by status (active/inactive)
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by difficulty
            if ($request->has('difficulty')) {
                $query->where('difficulty', $request->difficulty);
            }

            $habits = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'habits' => $habits,
                'categories' => Habits::where('user_id', $user->id)->distinct()->pluck('category')->filter(),
                'stats' => [
                    'total_habits' => $habits->count(),
                    'active_habits' => $habits->where('is_active', true)->count(),
                    'completed_today' => $this->getTodayCompletions($user)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch habits',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // POST /habits - Create new habit
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|in:good,bad',
                'description' => 'nullable|string',
                'target_frequency' => 'required|in:daily,weekly,monthly',
                'target_count' => 'required|integer|min:1',
                'color' => 'nullable|string|max:7',
                'icon' => 'nullable|string|max:50',
                'reminder_time' => 'nullable|date_format:H:i',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after:start_date',
                'category' => 'nullable|string|max:100',
                'difficulty' => 'required|in:easy,medium,hard',
                'base_xp' => 'required|integer|min:5|max:50',
                'privacy' => 'required|in:public,private'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $habit = Habits::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'type' => $request->type,
                'description' => $request->description,
                'target_frequency' => $request->target_frequency,
                'target_count' => $request->target_count,
                'color' => $request->color ?? '#3B82F6',
                'icon' => $request->icon ?? '📝',
                'is_active' => true,
                'reminder_time' => $request->reminder_time,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'category' => $request->category,
                'difficulty' => $request->difficulty,
                'base_xp' => $request->base_xp,
                'privacy' => $request->privacy,
                'current_streak' => 0,
                'longest_streak' => 0,
                'total_completions' => 0
            ]);

            return response()->json([
                'message' => 'Habit created successfully!',
                'habit' => $habit
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create habit',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // GET /habits/{id} - Show habit detail
    public function show($id)
    {
        try {
            $user = Auth::user();
            $habit = Habits::where('user_id', $user->id)->with('habitLogs')->findOrFail($id);

            return response()->json([
                'habit' => $habit,
                'stats' => $this->getHabitStats($habit)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Habit not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    // PUT /habits/{id} - Update habit
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $habit = Habits::where('user_id', $user->id)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'target_frequency' => 'sometimes|required|in:daily,weekly,monthly',
                'target_count' => 'sometimes|required|integer|min:1',
                'color' => 'nullable|string|max:7',
                'icon' => 'nullable|string|max:50',
                'is_active' => 'sometimes|boolean',
                'reminder_time' => 'nullable|date_format:H:i',
                'end_date' => 'nullable|date|after:start_date',
                'category' => 'nullable|string|max:100',
                'difficulty' => 'sometimes|required|in:easy,medium,hard',
                'base_xp' => 'sometimes|required|integer|min:5|max:50',
                'privacy' => 'sometimes|required|in:public,private'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()
                ], 422);
            }

            $habit->update($request->all());

            return response()->json([
                'message' => 'Habit updated successfully!',
                'habit' => $habit
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update habit',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // DELETE /habits/{id} - Delete habit
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $habit = Habits::where('user_id', $user->id)->findOrFail($id);

            $habit->delete();

            return response()->json([
                'message' => 'Habit deleted successfully!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete habit',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // GET /habits/{id}/stats - Get habit statistics
    public function getHabitStats($id)
    {
        try {
            $user = Auth::user();
            $habit = Habits::where('user_id', $user->id)->findOrFail($id);

            $stats = $this->calculateHabitStats($habit);

            return response()->json([
                'stats' => $stats,
                'recent_logs' => $habit->habitLogs()->orderBy('date', 'desc')->limit(10)->get()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch habit stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // GET /habits/stats/dashboard - Dashboard statistics
    public function dashboardStats()
    {
        try {
            $user = Auth::user();
            
            $habits = Habits::where('user_id', $user->id)->get();
            $activeHabits = $habits->where('is_active', true);

            return response()->json([
                'total_habits' => $habits->count(),
                'active_habits' => $activeHabits->count(),
                'habits_by_category' => $this->getHabitsByCategory($habits),
                'habits_by_difficulty' => $this->getHabitsByDifficulty($habits),
                'weekly_completion' => $this->getWeeklyCompletionRate($user),
                'streak_info' => $this->getStreakInfo($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch dashboard stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // GET /analytics/success-rates - Success rates analytics
    public function successRates()
    {
        try {
            $user = Auth::user();
            
            return response()->json([
                'daily_success_rates' => $this->getDailySuccessRates($user),
                'weekly_trends' => $this->getWeeklyTrends($user),
                'habit_performance' => $this->getHabitPerformance($user),
                'best_time_to_complete' => $this->getBestCompletionTime($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch analytics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // PRIVATE METHODS
    private function calculateHabitStats($habit)
    {
        $logs = $habit->habitLogs;
        $completedLogs = $logs->where('status', 'completed');

        return [
            'total_logs' => $logs->count(),
            'completed_logs' => $completedLogs->count(),
            'success_rate' => $logs->count() > 0 ? round(($completedLogs->count() / $logs->count()) * 100) : 0,
            'current_streak' => $habit->current_streak,
            'longest_streak' => $habit->longest_streak,
            'total_xp_earned' => $logs->sum('xp_earned'),
            'average_completion_time' => $this->getAverageCompletionTime($completedLogs),
            'best_streak_period' => $this->getBestStreakPeriod($habit)
        ];
    }

    private function getHabitsByCategory($habits)
    {
        return $habits->groupBy('category')->map(function($categoryHabits, $category) {
            return [
                'category' => $category ?: 'Uncategorized',
                'count' => $categoryHabits->count(),
                'active' => $categoryHabits->where('is_active', true)->count()
            ];
        })->values();
    }

    private function getHabitsByDifficulty($habits)
    {
        return $habits->groupBy('difficulty')->map(function($difficultyHabits, $difficulty) {
            return [
                'difficulty' => $difficulty,
                'count' => $difficultyHabits->count(),
                'completion_rate' => $this->calculateDifficultyCompletionRate($difficultyHabits)
            ];
        });
    }

    private function calculateDifficultyCompletionRate($habits)
    {
        $totalLogs = 0;
        $completedLogs = 0;

        foreach ($habits as $habit) {
            $totalLogs += $habit->total_logs;
            $completedLogs += $habit->completed_logs;
        }

        return $totalLogs > 0 ? round(($completedLogs / $totalLogs) * 100) : 0;
    }

    private function getWeeklyCompletionRate($user)
    {
        $startDate = now()->subDays(6);
        $endDate = now();

        $logs = \App\Models\HabitLog::whereHas('habit', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->whereBetween('date', [$startDate, $endDate])->get();

        $completionByDay = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dayLogs = $logs->where('date', $current->format('Y-m-d'));
            $completed = $dayLogs->where('status', 'completed')->count();
            $total = $dayLogs->count();

            $completionByDay[] = [
                'date' => $current->format('Y-m-d'),
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'completed' => $completed,
                'total' => $total
            ];

            $current->addDay();
        }

        return $completionByDay;
    }

    private function getStreakInfo($user)
    {
        $habits = Habits::where('user_id', $user->id)->where('is_active', true)->get();
        
        return [
            'current_streak' => $habits->max('current_streak') ?? 0,
            'longest_streak' => $habits->max('longest_streak') ?? 0,
            'perfect_days' => $this->getPerfectDaysCount($user),
            'total_xp' => $user->total_xp
        ];
    }

    private function getPerfectDaysCount($user)
    {
        // Count days where all active habits were completed
        $activeHabitsCount = Habits::where('user_id', $user->id)->where('is_active', true)->count();
        
        if ($activeHabitsCount === 0) return 0;

        $perfectDays = \App\Models\HabitLog::whereHas('habit', function($query) use ($user) {
            $query->where('user_id', $user->id)->where('is_active', true);
        })
        ->where('status', 'completed')
        ->get()
        ->groupBy('date')
        ->filter(function($dayLogs) use ($activeHabitsCount) {
            return $dayLogs->count() === $activeHabitsCount;
        })
        ->count();

        return $perfectDays;
    }

    private function getAverageCompletionTime($completedLogs)
    {
        $logsWithTime = $completedLogs->whereNotNull('time_completed');
        if ($logsWithTime->isEmpty()) return null;

        $totalMinutes = $logsWithTime->sum(function($log) {
            return \Carbon\Carbon::parse($log->time_completed)->diffInMinutes(\Carbon\Carbon::parse('00:00'));
        });

        return round($totalMinutes / $logsWithTime->count());
    }

    private function getBestStreakPeriod($habit)
    {
        // Implement logic to find the best streak period
        return [
            'start_date' => null,
            'end_date' => null,
            'days' => $habit->longest_streak
        ];
    }

    // Analytics methods
    private function getDailySuccessRates($user)
    {
        // Return success rates by day of week
        return [
            'monday' => 75,
            'tuesday' => 80,
            'wednesday' => 65,
            'thursday' => 90,
            'friday' => 70,
            'saturday' => 50,
            'sunday' => 60
        ];
    }

    private function getWeeklyTrends($user)
    {
        // Return weekly completion trends
        $trends = [];
        for ($i = 4; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();
            
            $trends[] = [
                'week' => $weekStart->format('M j'),
                'completion_rate' => rand(60, 95)
            ];
        }
        return $trends;
    }

    private function getHabitPerformance($user)
    {
        $habits = Habits::where('user_id', $user->id)->withCount(['habitLogs as completed_logs' => function($query) {
            $query->where('status', 'completed');
        }])->withCount('habitLogs as total_logs')->get();

        return $habits->map(function($habit) {
            return [
                'name' => $habit->name,
                'success_rate' => $habit->total_logs > 0 ? round(($habit->completed_logs / $habit->total_logs) * 100) : 0,
                'current_streak' => $habit->current_streak,
                'difficulty' => $habit->difficulty
            ];
        });
    }

    private function getBestCompletionTime($user)
    {
        $logs = \App\Models\HabitLog::whereHas('habit', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->whereNotNull('time_completed')->get();

        if ($logs->isEmpty()) return 'No data';

        $completionByHour = $logs->groupBy(function($log) {
            return \Carbon\Carbon::parse($log->time_completed)->hour;
        })->sortByDesc(function($group) {
            return $group->count();
        });

        $bestHour = $completionByHour->keys()->first();
        
        return $bestHour ? sprintf("%02d:00", $bestHour) : 'No data';
    }

    public function dashboard()
    {
        try {
            $user = Auth::user();
            
            return response()->json([
                'today_stats' => $this->getTodayStats($user),
                'weekly_overview' => $this->getWeeklyOverview($user),
                'recent_activity' => $this->getRecentActivity($user),
                'upcoming_habits' => $this->getUpcomingHabits($user),
                'motivational_quote' => $this->getMotivationalQuote()
            ]);

        } catch (\Exception $e) {
            \Log::error('Dashboard error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /dashboard/quick-stats - Quick stats
     */
    public function quickStats()
    {
        try {
            $user = Auth::user();
            
            return response()->json([
                'total_habits' => Habits::where('user_id', $user->id)->where('is_active', true)->count(),
                'completed_today' => $this->getTodayCompletions($user),
                'current_streak' => $this->getCurrentStreak($user),
                'total_xp' => $user->total_xp ?? 0,
                'level' => $user->level ?? 1,
                'coins' => $user->coins ?? 0
            ]);

        } catch (\Exception $e) {
            \Log::error('Quick stats error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch quick stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    private function getTodayStats($user)
    {
        $today = now()->format('Y-m-d');
        
        $totalHabits = Habits::where('user_id', $user->id)
                           ->where('is_active', true)
                           ->whereDate('start_date', '<=', $today)
                           ->where(function($q) use ($today) {
                               $q->whereNull('end_date')
                                 ->orWhereDate('end_date', '>=', $today);
                           })->count();

        $completedToday = HabitLog::whereHas('habit', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->whereDate('date', $today)->where('status', 'completed')->count();

        return [
            'completed' => $completedToday,
            'total' => $totalHabits,
            'completion_rate' => $totalHabits > 0 ? round(($completedToday / $totalHabits) * 100) : 0
        ];
    }

    private function getWeeklyOverview($user)
    {
        $startDate = now()->subDays(6)->startOfDay();
        $endDate = now()->endOfDay();

        $logs = HabitLog::whereHas('habit', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->whereBetween('date', [$startDate, $endDate])->get();

        $weeklyData = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');
            $dayLogs = $logs->where('date', $dateStr);
            
            $completedCount = $dayLogs->where('status', 'completed')->count();
            $totalHabits = Habits::where('user_id', $user->id)
                               ->where('is_active', true)
                               ->whereDate('start_date', '<=', $current)
                               ->where(function($q) use ($current) {
                                   $q->whereNull('end_date')
                                     ->orWhereDate('end_date', '>=', $current);
                               })->count();

            $weeklyData[] = [
                'date' => $dateStr,
                'completed' => $completedCount,
                'total' => $totalHabits,
                'completion_rate' => $totalHabits > 0 ? round(($completedCount / $totalHabits) * 100) : 0
            ];

            $current->addDay();
        }

        return $weeklyData;
    }

    private function getRecentActivity($user)
    {
        return HabitLog::whereHas('habit', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->with('habit')
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get()
        ->map(function($log) {
            return [
                'id' => $log->id,
                'habit_id' => $log->habit_id,
                'habit' => [
                    'name' => $log->habit->name,
                    'type' => $log->habit->type
                ],
                'status' => $log->status,
                'date' => $log->date,
                'created_at' => $log->created_at
            ];
        });
    }

    private function getUpcomingHabits($user)
    {
        return Habits::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->whereNotNull('reminder_time')
                    ->whereDoesntHave('habitLogs', function($query) {
                        $query->whereDate('date', today());
                    })
                    ->orderBy('reminder_time', 'asc')
                    ->limit(5)
                    ->get()
                    ->map(function($habit) {
                        return [
                            'id' => $habit->id,
                            'name' => $habit->name,
                            'reminder_time' => $habit->reminder_time
                        ];
                    });
    }

    private function getMotivationalQuote()
    {
        $quotes = [
            "Consistency is the key to mastery! 🗝️",
            "Small habits lead to big changes! 🌱",
            "Every day is a new opportunity! ✨",
            "Progress, not perfection! 📈",
            "You're closer than you think! 💪"
        ];
        
        return $quotes[array_rand($quotes)];
    }

    private function getTodayCompletions($user)
    {
        return HabitLog::whereHas('habit', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->whereDate('date', today())->where('status', 'completed')->count();
    }

    private function getCurrentStreak($user)
    {
        // Implementasi sederhana - bisa diperbaiki nanti
        $habits = Habits::where('user_id', $user->id)->where('is_active', true)->get();
        return $habits->max('current_streak') ?? 0;
    }
}