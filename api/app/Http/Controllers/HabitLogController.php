<?php

namespace App\Http\Controllers;

use App\Models\HabitLog;
use App\Models\Habits;
use App\Models\User;
use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\BadgeService;

class HabitLogController extends Controller
{
public function index(Request $request)
{
    try {
        $user = Auth::user();
        
        // Dapatkan habit IDs milik user
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        
        $query = HabitLog::with('habit')
            ->whereIn('habit_id', $userHabitIds)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Filter by habit type
        if ($request->has('habit_type')) {
            $query->whereHas('habit', function($q) use ($request) {
                $q->where('type', $request->habit_type);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->whereHas('habit', function($q) use ($request) {
                $q->where('category', $request->category);
            });
        }

        // Filter by mood
        if ($request->has('mood')) {
            $query->where('mood', $request->mood);
        }

        // Filter by completion value (partial completion)
        if ($request->has('min_completion')) {
            $query->where('completion_value', '>=', $request->min_completion);
        }

        // PERBAIKAN: Gunakan get() untuk mendapatkan array, bukan paginate()
        $logs = $query->get();

        return response()->json([
            'logs' => $logs,  // ← Sekarang ini array, bukan Pagination object
            'filters' => $request->all(),
            'stats' => $this->calculateLogStats($user, $request)
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to fetch habit logs',
            'message' => $e->getMessage()
        ], 500);
    }
}

    // GET /habit-logs/calendar - Data untuk calendar view (UPDATED)
   public function calendarData(Request $request)
{
    try {
        $debugInfo = [];
        
        // 1. Check Authentication
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error' => 'Authentication failed',
                'message' => 'User not authenticated',
                'debug' => ['auth_check' => 'FAILED']
            ], 401);
        }
        $debugInfo['user_id'] = $user->id;
        $debugInfo['auth_check'] = 'PASSED';

        // 2. Get request parameters
        $year = $request->get('year', date('Y'));
        $month = $request->get('month', date('m'));
        $debugInfo['request_params'] = [
            'year' => $year,
            'month' => $month,
            'received_year' => $request->has('year'),
            'received_month' => $request->has('month')
        ];

        // 3. Calculate date range
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $debugInfo['date_range'] = [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'days_in_month' => $startDate->diffInDays($endDate) + 1
        ];

        // 4. Check user habits
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        $debugInfo['user_habits_count'] = $userHabitIds->count();
        $debugInfo['user_habit_ids'] = $userHabitIds;

        if ($userHabitIds->isEmpty()) {
            return response()->json([
                'calendar_data' => [],
                'month_stats' => [
                    'total_days' => $startDate->diffInDays($endDate) + 1,
                    'active_days' => 0,
                    'perfect_days' => 0,
                    'total_xp' => 0,
                    'average_completion_rate' => 0
                ],
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'month' => $startDate->format('F Y')
                ],
                'message' => 'No habits found for user',
                'debug' => $debugInfo
            ]);
        }

        // 5. Get logs for the date range
        $logs = HabitLog::with('habit')
            ->whereIn('habit_id', $userHabitIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $debugInfo['logs_found_count'] = $logs->count();
        $debugInfo['logs_date_range'] = $logs->isNotEmpty() ? [
            'earliest_log' => $logs->min('date'),
            'latest_log' => $logs->max('date')
        ] : 'No logs found';

        if ($logs->isEmpty()) {
            return $this->generateEmptyCalendar($startDate, $endDate, $debugInfo);
        }

        // 6. Process calendar data
        $logsByDate = $logs->groupBy('date');
        $debugInfo['unique_days_with_logs'] = $logsByDate->count();

        $calendarData = [];
        $current = $startDate->copy();
        
        $processedDays = 0;
        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');
            $dayLogs = $logsByDate->get($dateStr, collect());
            
            $completedCount = $dayLogs->where('status', 'completed')->count();
            
            $totalHabits = Habits::where('user_id', $user->id)
                                ->where('is_active', true)
                                ->where(function($q) use ($current) {
                                    $q->whereNull('start_date')
                                      ->orWhere('start_date', '<=', $current->format('Y-m-d'));
                                })
                                ->where(function($q) use ($current) {
                                    $q->whereNull('end_date')
                                      ->orWhere('end_date', '>=', $current->format('Y-m-d'));
                                })
                                ->count();
            
            $completionRate = $totalHabits > 0 ? round(($completedCount / $totalHabits) * 100) : 0;
            
            $calendarData[$dateStr] = [
                'date' => $dateStr,
                'completed_count' => $completedCount,
                'total_habits' => $totalHabits,
                'completion_rate' => $completionRate,
                'xp_earned' => $dayLogs->sum('xp_earned'),
                'is_perfect_day' => $totalHabits > 0 && $completedCount === $totalHabits,
                'mood_summary' => $this->getDayMoodSummary($dayLogs),
                'habits_completed' => $dayLogs->where('status', 'completed')
                                            ->pluck('habit.name')
                                            ->filter()
                                            ->values()
            ];
            
            $current->addDay();
            $processedDays++;
        }

        $debugInfo['processed_days_count'] = $processedDays;
        $debugInfo['calendar_entries_created'] = count($calendarData);

        return response()->json([
            'calendar_data' => $calendarData,
            'month_stats' => $this->calculateMonthStats($user, $startDate, $endDate, $logs),
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'month' => $startDate->format('F Y')
            ],
            'debug' => $debugInfo
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to fetch calendar data',
            'message' => $e->getMessage(),
            'debug' => [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        ], 500);
    }
}

    // POST /habit-logs - Manual log habit (UPDATED dengan field baru)
    public function store(Request $request)
{
    try {
        $user = Auth::user();
        
        // VALIDASI LENGKAP
        $validator = \Validator::make($request->all(), [
            'habit_id' => 'required|exists:habits,id',
            'date' => 'required|date',
            'status' => 'required|in:completed,missed,skipped,partial',
            'completion_value' => 'nullable|numeric|min:0|max:1',
            'notes' => 'nullable|string|max:1000',
            'time_completed' => 'nullable|date_format:H:i',
            'mood' => 'nullable|in:terrible,bad,neutral,good,excellent',
            'difficulty_rating' => 'nullable|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        // CHECK HABIT OWNERSHIP
        $habit = Habits::where('user_id', $user->id)->find($request->habit_id);
        if (!$habit) {
            return response()->json([
                'error' => 'Habit not found or not owned by user'
            ], 404);
        }

        // CHECK DUPLICATE
        $existingLog = HabitLog::where('habit_id', $request->habit_id)
            ->whereDate('date', $request->date)
            ->first();

        if ($existingLog) {
            return response()->json([
                'error' => 'Already logged for this date',
                'existing_log' => $existingLog
            ], 400);
        }

        // CALCULATE XP berdasarkan completion value
        $xpEarned = 0;
        $streakBonus = 0;

        if ($request->status === 'completed' || ($request->status === 'partial' && $request->completion_value > 0)) {
            $baseXP = $habit->base_xp ?? 10;
            
            // Adjust XP berdasarkan completion value
            $completionValue = $request->completion_value ?? 1.0;
            $xpEarned = round($baseXP * $completionValue);
            
            // Streak bonus hanya untuk full completion
            if ($completionValue >= 1.0) {
                $streakBonus = $this->calculateStreakBonus($habit->current_streak ?? 0);
                $xpEarned += $streakBonus;
            }
        }

        // CREATE LOG dengan field baru
        $habitLog = HabitLog::create([
            'habit_id' => $request->habit_id,
            'user_id' => $user->id,
            'date' => $request->date,
            'status' => $request->status,
            'completion_value' => $request->completion_value ?? ($request->status === 'completed' ? 1.0 : 0.0),
            'notes' => $request->notes,
            'time_completed' => $request->time_completed,
            'mood' => $request->mood,
            'difficulty_rating' => $request->difficulty_rating,
            'xp_earned' => $xpEarned,
            'streak_bonus' => $streakBonus
        ]);

        // UPDATE USER PROGRESS
        if ($xpEarned > 0) {
            $user->current_xp += $xpEarned;
            $user->total_xp += $xpEarned;
            $user->coins += ceil($xpEarned / 2);
            $user->save();

            // Check level up
            $this->checkLevelUp($user);
        }

        // UPDATE HABIT STREAK (hanya untuk full completion)
        if ($request->status === 'completed' && ($request->completion_value ?? 1.0) >= 1.0) {
            $this->updateHabitStreak($habit, $request->status, $request->date);
        } else if ($request->status === 'missed' || $request->status === 'skipped') {
            $habit->current_streak = 0;
            $habit->save();
        }

        // ✅ BARU: CHECK BADGES SETELAH LOG DIBUAT
        $badgeService = new BadgeService();
        $awardedBadges = $badgeService->checkAndAwardBadges($user);

        return response()->json([
            'message' => 'Habit log berhasil dibuat!',
            'log' => $habitLog->load('habit'),
            'xp_earned' => $xpEarned,
            'awarded_badges' => $awardedBadges, // ✅ BARU: Info badges yang didapat
            'user' => [
                'level' => $user->level,
                'current_xp' => $user->current_xp,
                'coins' => $user->coins
            ]
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to create habit log',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
}

   // GET /habit-logs/{id} - Detail log (UPDATED)
public function show($habit_log)
{
    try {
        $user = Auth::user();
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        
        $log = HabitLog::with('habit')
            ->where('id', $habit_log)
            ->whereIn('habit_id', $userHabitIds)
            ->first();  // ← PERBAIKAN: first(), bukan firs()

        if (!$log) {
            return response()->json([
                'error' => 'Habit log not found',
                'message' => 'The requested habit log does not exist or you do not have permission to access it'
            ], 404);
        }

        return response()->json([
            'log' => $log,
            'related_logs' => $this->getRelatedLogs($log)
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to fetch habit log',
            'message' => $e->getMessage()
        ], 500);
    }
}

    // PUT /habit-logs/{id} - Update log (UPDATED)
    public function update(Request $request, $habit_log)
{
    try {
        $user = Auth::user();
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        
        $validator = \Validator::make($request->all(), [
            'status' => 'sometimes|required|in:completed,missed,skipped,partial',
            'completion_value' => 'nullable|numeric|min:0|max:1',
            'notes' => 'nullable|string|max:1000',
            'time_completed' => 'nullable|date_format:H:i',
            'mood' => 'nullable|in:terrible,bad,neutral,good,excellent',
            'difficulty_rating' => 'nullable|integer|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        // PERBAIKAN: Typo - 'firs()' menjadi 'first()'
        $log = HabitLog::with('habit')
            ->where('id', $habit_log)
            ->whereIn('habit_id', $userHabitIds)
            ->first(); // ✅ PERBAIKAN: first() bukan firs()

        if (!$log) {
            return response()->json([
                'error' => 'Habit log not found'
            ], 404);
        }

        $oldStatus = $log->status;
        $oldCompletionValue = $log->completion_value;
        $newStatus = $request->status ?? $log->status;
        $newCompletionValue = $request->completion_value ?? $log->completion_value;

        // Jika status atau completion value berubah, perlu recalculate XP
        if ($oldStatus !== $newStatus || $oldCompletionValue != $newCompletionValue) {
            $xpEarned = 0;
            $streakBonus = 0;

            if ($newStatus === 'completed' || ($newStatus === 'partial' && $newCompletionValue > 0)) {
                $baseXP = $log->habit->base_xp;
                $xpEarned = round($baseXP * $newCompletionValue);
                
                if ($newCompletionValue >= 1.0) {
                    $streakBonus = $this->calculateStreakBonus($log->habit->current_streak);
                    $xpEarned += $streakBonus;
                }
            }

            // Calculate XP difference
            $xpDifference = $xpEarned - $log->xp_earned;
            
            // Update log
            $log->xp_earned = $xpEarned;
            $log->streak_bonus = $streakBonus;

            // Update user progress berdasarkan selisih XP
            if ($xpDifference !== 0) {
                $this->updateUserProgress($user, $xpDifference);
            }
        }

        $log->update($request->only([
            'status', 'completion_value', 'notes', 'time_completed', 
            'mood', 'difficulty_rating'
        ]));

        // Update streak jika status berubah dan full completion
        if ($oldStatus !== $newStatus) {
            if ($newStatus === 'completed' && $newCompletionValue >= 1.0) {
                $this->updateHabitStreak($log->habit, $newStatus, $log->date);
            } else if ($newStatus === 'missed' || $newStatus === 'skipped') {
                $log->habit->current_streak = 0;
                $log->habit->save();
            }
        }

        // ✅ BARU: CHECK BADGES SETELAH LOG DIUPDATE
        $badgeService = new BadgeService();
        $awardedBadges = $badgeService->checkAndAwardBadges($user);

        return response()->json([
            'message' => 'Habit log berhasil diupdate!',
            'log' => $log->load('habit'),
            'awarded_badges' => $awardedBadges // ✅ BARU: Info badges yang didapat
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to update habit log',
            'message' => $e->getMessage()
        ], 500);
    }
}


    // DELETE /habit-logs/{id} - Hapus log
    public function destroy($habit_log)
    {
        try {
            $user = Auth::user();
            $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
            
            $log = HabitLog::with('habit')
                ->where('id', $habit_log)
                ->whereIn('habit_id', $userHabitIds)
                ->first();

            // Kurangi XP user sebelum hapus
            if ($log->xp_earned > 0) {
                $user->current_xp = max(0, $user->current_xp - $log->xp_earned);
                $user->total_xp = max(0, $user->total_xp - $log->xp_earned);
                $user->coins = max(0, $user->coins - ceil($log->xp_earned / 2));
                $user->save();
            }

            $log->delete();

            return response()->json([
                'message' => 'Habit log berhasil dihapus!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete habit log',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // GET /habit-logs/stats/overview - Stats overview (UPDATED)
    public function statsOverview()
    {
        try {
            $user = Auth::user();

            return response()->json([
                'lifetime_stats' => $this->calculateLifetimeStats($user),
                'monthly_trends' => $this->calculateMonthlyTrends($user),
                'habit_performance' => $this->calculateHabitPerformance($user),
                'mood_analytics' => $this->calculateMoodAnalytics($user),
                'completion_insights' => $this->calculateCompletionInsights($user)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch stats overview',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // GET /habit-logs/streaks - Get user streaks data (UPDATED)
   public function getStreaks(Request $request)
{
    try {
        $debugInfo = [];
        
        // 1. Check Authentication
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error' => 'Authentication failed',
                'message' => 'User not authenticated',
                'debug' => ['auth_check' => 'FAILED']
            ], 401);
        }
        $debugInfo['user_id'] = $user->id;
        $debugInfo['auth_check'] = 'PASSED';

        // 2. Check if user has active habits
        $userHabits = Habits::where('user_id', $user->id)->where('is_active', true)->get();
        $debugInfo['active_habits_count'] = $userHabits->count();
        $debugInfo['active_habits_ids'] = $userHabits->pluck('id');

        if ($userHabits->isEmpty()) {
            return response()->json([
                'current_streak' => 0,
                'longest_streak' => 0,
                'total_completed' => 0,
                'active_days' => 0,
                'message' => 'No active habits found',
                'debug' => $debugInfo
            ]);
        }

        // 3. Get user's habit IDs
        $userHabitIds = $userHabits->pluck('id');
        $debugInfo['user_habit_ids'] = $userHabitIds;

        // 4. Check completed logs
        $completedLogs = HabitLog::whereIn('habit_id', $userHabitIds)
            ->where('status', 'completed')
            ->where('completion_value', '>=', 1.0)
            ->orderBy('date', 'asc')
            ->get();

        $debugInfo['completed_logs_count'] = $completedLogs->count();
        $debugInfo['completed_logs_dates'] = $completedLogs->pluck('date')->take(5); // First 5 dates

        if ($completedLogs->isEmpty()) {
            return response()->json([
                'current_streak' => 0,
                'longest_streak' => 0,
                'total_completed' => 0,
                'active_days' => 0,
                'message' => 'No completed habits found',
                'debug' => $debugInfo
            ]);
        }

        // 5. Calculate streaks
        $streaks = $this->calculateAdvancedStreaks($completedLogs);
        $debugInfo['streaks_calculation'] = 'COMPLETED';
        $debugInfo['calculated_current_streak'] = $streaks['current'];
        $debugInfo['calculated_longest_streak'] = $streaks['longest'];

        return response()->json([
            'current_streak' => $streaks['current'],
            'longest_streak' => $streaks['longest'],
            'total_completed' => $completedLogs->count(),
            'active_days' => $completedLogs->groupBy('date')->count(),
            'streak_breakdown' => $streaks['breakdown'],
            'debug' => $debugInfo
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to calculate streaks',
            'message' => $e->getMessage(),
            'debug' => [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        ], 500);
    }
}
    // GET /reports/weekly - Weekly report data (UPDATED)
    public function weeklyReport(Request $request)
    {
        try {
            $user = Auth::user();
            $userHabitIds = Habits::where('user_id', $user->id)->where('is_active', true)->pluck('id');
            
            // Get logs for the last 7 days
            $startDate = Carbon::now()->subDays(6)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
            
            $logs = HabitLog::whereIn('habit_id', $userHabitIds)
                ->whereBetween('date', [$startDate, $endDate])
                ->get()
                ->groupBy('date');

            $weeklyData = [];
            $current = $startDate->copy();
            
            while ($current <= $endDate) {
                $dateStr = $current->format('Y-m-d');
                $dayLogs = $logs->get($dateStr, collect());
                
                $completedCount = $dayLogs->where('status', 'completed')->count();
                $totalHabits = Habits::where('user_id', $user->id)
                                    ->where('is_active', true)
                                    ->whereDate('start_date', '<=', $current)
                                    ->where(function($q) use ($current) {
                                        $q->whereNull('end_date')
                                          ->orWhereDate('end_date', '>=', $current);
                                    })
                                    ->count();
                
                $weeklyData[] = [
                    'date' => $dateStr,
                    'completed' => $completedCount,
                    'total' => $totalHabits,
                    'completion_rate' => $totalHabits > 0 ? round(($completedCount / $totalHabits) * 100) : 0,
                    'xp_earned' => $dayLogs->sum('xp_earned'),
                    'mood' => $this->getAverageMood($dayLogs)
                ];
                
                $current->addDay();
            }

            return response()->json([
                'weekly_data' => $weeklyData,
                'stats' => [
                    'total_completed' => collect($weeklyData)->sum('completed'),
                    'total_xp' => collect($weeklyData)->sum('xp_earned'),
                    'average_completion_rate' => collect($weeklyData)->avg('completion_rate'),
                    'perfect_days' => collect($weeklyData)->where('completion_rate', 100)->count(),
                    'best_day' => collect($weeklyData)->sortByDesc('completion_rate')->first()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate weekly report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // GET /reports/monthly - Monthly report data (UPDATED)
    public function monthlyReport(Request $request)
    {
        try {
            $user = Auth::user();
            $userHabitIds = Habits::where('user_id', $user->id)->where('is_active', true)->pluck('id');
            
            // Get logs for the current month atau bulan yang diminta
            $year = $request->get('year', date('Y'));
            $month = $request->get('month', date('m'));
            
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            
            $logs = HabitLog::whereIn('habit_id', $userHabitIds)
                ->whereBetween('date', [$startDate, $endDate])
                ->get()
                ->groupBy('date');

            $monthlyData = [];
            $current = $startDate->copy();
            
            while ($current <= $endDate) {
                $dateStr = $current->format('Y-m-d');
                $dayLogs = $logs->get($dateStr, collect());
                
                $completedCount = $dayLogs->where('status', 'completed')->count();
                $totalHabits = Habits::where('user_id', $user->id)
                                    ->where('is_active', true)
                                    ->whereDate('start_date', '<=', $current)
                                    ->where(function($q) use ($current) {
                                        $q->whereNull('end_date')
                                          ->orWhereDate('end_date', '>=', $current);
                                    })
                                    ->count();
                
                $monthlyData[$dateStr] = [
                    'date' => $dateStr,
                    'completed' => $completedCount,
                    'total' => $totalHabits,
                    'completion_rate' => $totalHabits > 0 ? round(($completedCount / $totalHabits) * 100) : 0,
                    'xp_earned' => $dayLogs->sum('xp_earned'),
                    'is_perfect_day' => $totalHabits > 0 && $completedCount === $totalHabits
                ];
                
                $current->addDay();
            }

            return response()->json([
                'monthly_data' => $monthlyData,
                'stats' => [
                    'total_completed' => collect($monthlyData)->sum('completed'),
                    'total_xp' => collect($monthlyData)->sum('xp_earned'),
                    'average_completion_rate' => collect($monthlyData)->avg('completion_rate'),
                    'active_days' => collect($monthlyData)->where('completed', '>', 0)->count(),
                    'perfect_days' => collect($monthlyData)->where('is_perfect_day', true)->count(),
                    'month' => $startDate->format('F Y')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate monthly report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 🎮 PRIVATE GAMIFICATION METHODS
    private function calculateStreakBonus($currentStreak)
    {
        if ($currentStreak >= 30) return 20;
        if ($currentStreak >= 14) return 15;
        if ($currentStreak >= 7) return 10;
        if ($currentStreak >= 3) return 5;
        return 0;
    }

    private function updateHabitStreak($habit, $status, $date)
    {
        if ($status === 'completed') {
            $habit->increment('current_streak');
            $habit->increment('total_completions');
            
            if ($habit->current_streak > $habit->longest_streak) {
                $habit->longest_streak = $habit->current_streak;
            }
        } else {
            $habit->current_streak = 0;
        }
        
        $habit->save();
    }

    private function updateUserProgress($user, $xpEarned)
    {
        if ($xpEarned > 0) {
            $user->current_xp += $xpEarned;
            $user->total_xp += $xpEarned;
            $user->coins += ceil($xpEarned / 2);
            
            $this->checkLevelUp($user);
            $user->save();
        }
    }

    private function checkLevelUp($user)
    {
        $xpNeeded = $user->level * 100;
        
        while ($user->current_xp >= $xpNeeded) {
            $user->current_xp -= $xpNeeded;
            $user->level++;
            $xpNeeded = $user->level * 100;
            $user->coins += $user->level * 10;
            
            // Check for badge achievements
            $this->checkBadgeAchievements($user);
        }
    }

    private function checkBadgeAchievements($user)
    {
        try {
        // Cek apakah kolom requirement_value ada di tabel badges
        if (!\Schema::hasColumn('badges', 'requirement_value')) {
            \Log::warning('Badges table does not have requirement_value column');
            return;
        }

        // Check for badge achievements
        $badges = Badge::where('requirement_type', 'level')
                      ->where('requirement_value', '<=', $user->level)
                      ->whereDoesntHave('users', function($query) use ($user) {
                          $query->where('user_id', $user->id);
                      })
                      ->get();

        foreach ($badges as $badge) {
            $user->badges()->attach($badge->id, ['earned_at' => now()]);
            }

        } catch (\Exception $e) {
            \Log::error('Error in checkBadgeAchievements: ' . $e->getMessage());
        }
    }

    // 📊 STATS & ANALYTICS METHODS
    private function calculateLogStats($user, $request)
    {
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        
        $query = HabitLog::whereIn('habit_id', $userHabitIds);

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        $logs = $query->get();

        return [
            'total_logs' => $logs->count(),
            'completed_logs' => $logs->where('status', 'completed')->count(),
            'partial_logs' => $logs->where('status', 'partial')->count(),
            'success_rate' => $logs->count() > 0 ? 
                round(($logs->where('status', 'completed')->count() / $logs->count()) * 100) : 0,
            'total_xp' => $logs->sum('xp_earned'),
            'average_xp_per_day' => $this->calculateAverageXpPerDay($logs),
            'mood_distribution' => $this->calculateMoodDistribution($logs)
        ];
    }

    private function calculateMonthStats($user, $startDate, $endDate, $logs = null)
{
    if (!$logs) {
        return [
            'total_days' => $startDate->diffInDays($endDate) + 1,
            'active_days' => 0,
            'perfect_days' => 0,
            'total_xp' => 0,
            'average_completion_rate' => 0
        ];
    }

    $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
    
    $monthLogs = $logs->whereIn('habit_id', $userHabitIds);

    $totalHabits = Habits::where('user_id', $user->id)
                        ->where('is_active', true)
                        ->count();

    return [
        'total_days' => $startDate->diffInDays($endDate) + 1,
        'active_days' => $monthLogs->groupBy('date')->count(),
        'perfect_days' => $this->calculatePerfectDaysCount($user, $startDate, $endDate),
        'total_xp' => $monthLogs->sum('xp_earned'),
        'average_completion_rate' => $this->calculateAverageCompletionRate($monthLogs, $totalHabits, $startDate, $endDate),
    ];
}

    private function calculateLifetimeStats($user)
    {
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        
        $logs = HabitLog::whereIn('habit_id', $userHabitIds)->get();
        $habits = Habits::where('user_id', $user->id)->get();

        return [
            'total_days_tracked' => $logs->groupBy('date')->count(),
            'total_completions' => $logs->where('status', 'completed')->count(),
            'total_xp_earned' => $user->total_xp,
            'current_streak' => $this->getCurrentStreak($user),
            'longest_streak' => $this->getLongestStreak($user),
            'habits_created' => $habits->count(),
            'badges_earned' => $user->badges->count(),
            'total_coins' => $user->coins
        ];
    }

    private function calculateMonthlyTrends($user)
    {
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        $sixMonthsAgo = now()->subMonths(6)->startOfMonth();
        
        $monthlyData = HabitLog::whereIn('habit_id', $userHabitIds)
            ->where('date', '>=', $sixMonthsAgo)
            ->get()
            ->groupBy(function($log) {
                return $log->date->format('Y-m');
            })
            ->map(function($monthLogs, $month) {
                return [
                    'month' => $month,
                    'completions' => $monthLogs->where('status', 'completed')->count(),
                    'xp_earned' => $monthLogs->sum('xp_earned'),
                    'success_rate' => $monthLogs->count() > 0 ? 
                        round(($monthLogs->where('status', 'completed')->count() / $monthLogs->count()) * 100) : 0,
                    'active_days' => $monthLogs->groupBy('date')->count()
                ];
            })
            ->values();

        return $monthlyData;
    }

    private function calculateHabitPerformance($user)
    {
        return Habits::where('user_id', $user->id)
            ->withCount(['habitLogs as completed_logs' => function($query) {
                $query->where('status', 'completed');
            }])
            ->withCount('habitLogs as total_logs')
            ->get()
            ->map(function($habit) {
                return [
                    'habit_id' => $habit->id,
                    'habit_name' => $habit->name,
                    'type' => $habit->type,
                    'category' => $habit->category,
                    'difficulty' => $habit->difficulty,
                    'success_rate' => $habit->total_logs > 0 ? 
                        round(($habit->completed_logs / $habit->total_logs) * 100) : 0,
                    'current_streak' => $habit->current_streak,
                    'longest_streak' => $habit->longest_streak,
                    'total_completions' => $habit->completed_logs,
                    'average_completion_time' => $this->getHabitAverageCompletionTime($habit)
                ];
            });
    }

    // 🔧 HELPER METHODS
    private function generateEmptyCalendar($startDate, $endDate, $debugInfo = [])
{
    $calendarData = [];
    $current = $startDate->copy();
    
    while ($current <= $endDate) {
        $dateStr = $current->format('Y-m-d');
        $calendarData[$dateStr] = [
            'date' => $dateStr,
            'completed_count' => 0,
            'total_habits' => 0,
            'completion_rate' => 0,
            'xp_earned' => 0,
            'is_perfect_day' => false,
            'mood_summary' => null,
            'habits_completed' => []
        ];
        $current->addDay();
    }

    return response()->json([
        'calendar_data' => $calendarData,
        'month_stats' => [
            'total_days' => $startDate->diffInDays($endDate) + 1,
            'active_days' => 0,
            'perfect_days' => 0,
            'total_xp' => 0,
            'average_completion_rate' => 0
        ],
        'period' => [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'month' => $startDate->format('F Y')
        ],
        'message' => 'Empty calendar generated - no data found',
        'debug' => $debugInfo
    ]);
}
    private function getDayMoodSummary($dayLogs)
    {
        $moods = $dayLogs->whereNotNull('mood')->pluck('mood');
        if ($moods->isEmpty()) return null;

        $moodValues = [
            'terrible' => 1,
            'bad' => 2,
            'neutral' => 3,
            'good' => 4,
            'excellent' => 5
        ];

        $averageMood = $moods->map(function($mood) use ($moodValues) {
            return $moodValues[$mood] ?? 3;
        })->avg();

        return round($averageMood);
    }

    private function getAverageMood($dayLogs)
    {
        $moods = $dayLogs->whereNotNull('mood')->pluck('mood');
        if ($moods->isEmpty()) return null;

        $moodCounts = $moods->countBy();
        return $moodCounts->sortDesc()->keys()->first();
    }

    private function calculateAdvancedStreaks($completedLogs)
    {
        $dates = $completedLogs->pluck('date')->unique()->sort();
        
        if ($dates->isEmpty()) {
            return ['current' => 0, 'longest' => 0, 'breakdown' => []];
        }

        $currentStreak = 0;
        $longestStreak = 0;
        $tempStreak = 0;
        $streakStart = null;
        $streaks = [];
        
        $previousDate = null;
        
        foreach ($dates as $date) {
            $currentDate = Carbon::parse($date);
            
            if ($previousDate === null) {
                $tempStreak = 1;
                $streakStart = $currentDate;
            } else {
                $diffInDays = $previousDate->diffInDays($currentDate);
                if ($diffInDays === 1) {
                    $tempStreak++;
                } else {
                    // Simpan streak yang berakhir
                    $streaks[] = [
                        'start' => $streakStart->format('Y-m-d'),
                        'end' => $previousDate->format('Y-m-d'),
                        'days' => $tempStreak
                    ];
                    
                    $tempStreak = 1;
                    $streakStart = $currentDate;
                }
            }
            
            $longestStreak = max($longestStreak, $tempStreak);
            $previousDate = $currentDate;
        }

        // Simpan streak terakhir
        if ($tempStreak > 0) {
            $streaks[] = [
                'start' => $streakStart->format('Y-m-d'),
                'end' => $previousDate->format('Y-m-d'),
                'days' => $tempStreak
            ];
        }
        
        // Check current streak (consecutive days sampai hari ini)
        $today = Carbon::today();
        $lastDate = Carbon::parse($dates->last());
        
        if ($lastDate->diffInDays($today) === 1) {
            $currentStreak = $tempStreak;
        } else if ($lastDate->isToday()) {
            $currentStreak = $tempStreak;
        } else {
            $currentStreak = 0;
        }

        return [
            'current' => $currentStreak,
            'longest' => $longestStreak,
            'breakdown' => $streaks
        ];
    }

    private function getRelatedLogs($log)
    {
        if (!$log) {
            return [];
        }
        
        return HabitLog::where('habit_id', $log->habit_id)
            ->where('id', '!=', $log->id)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get();
    }

    private function calculateAverageXpPerDay($logs)
    {
        $days = $logs->groupBy('date')->count();
        if ($days === 0) return 0;
        
        return round($logs->sum('xp_earned') / $days);
    }

    private function calculateMoodDistribution($logs)
    {
        $moods = $logs->whereNotNull('mood')->pluck('mood');
        return $moods->countBy()->all();
    }

    private function calculatePerfectDaysCount($user, $start, $end)
    {
        $perfectDays = 0;
        $activeHabitsCount = Habits::where('user_id', $user->id)
                                 ->where('is_active', true)
                                 ->count();
        
        if ($activeHabitsCount === 0) return 0;

        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        $current = $start->copy();
        
        while ($current <= $end) {
            $dayCompletions = HabitLog::whereIn('habit_id', $userHabitIds)
                ->whereDate('date', $current)
                ->where('status', 'completed')
                ->count();
                
            if ($dayCompletions === $activeHabitsCount) {
                $perfectDays++;
            }
            
            $current->addDay();
        }
        
        return $perfectDays;
    }

    private function calculateAverageCompletionRate($logs, $totalHabits, $startDate, $endDate)
    {
        if ($totalHabits === 0) return 0;

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalPossibleCompletions = $totalDays * $totalHabits;
        $actualCompletions = $logs->where('status', 'completed')->count();

        return $totalPossibleCompletions > 0 ? 
            round(($actualCompletions / $totalPossibleCompletions) * 100) : 0;
    }

    private function getMostProductiveDay($logs)
    {
        $completionByDay = $logs->where('status', 'completed')
                               ->groupBy(function($log) {
                                   return $log->date->format('l');
                               })
                               ->map(function($dayLogs, $day) {
                                   return $dayLogs->count();
                               });

        return $completionByDay->sortDesc()->keys()->first() ?? 'No data';
    }

    private function getCurrentStreak($user)
    {
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        $lastCompletion = HabitLog::whereIn('habit_id', $userHabitIds)
                                ->where('status', 'completed')
                                ->where('completion_value', '>=', 1.0)
                                ->orderBy('date', 'desc')
                                ->first();

        if (!$lastCompletion) return 0;

        $currentStreak = 0;
        $currentDate = Carbon::today();
        $checkDate = Carbon::parse($lastCompletion->date);

        while ($checkDate <= $currentDate) {
            $dayCompletions = HabitLog::whereIn('habit_id', $userHabitIds)
                                    ->whereDate('date', $checkDate)
                                    ->where('status', 'completed')
                                    ->where('completion_value', '>=', 1.0)
                                    ->count();

            if ($dayCompletions > 0) {
                $currentStreak++;
                $checkDate->addDay();
            } else {
                break;
            }
        }

        return $currentStreak;
    }

    private function getLongestStreak($user)
    {
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        $completedLogs = HabitLog::whereIn('habit_id', $userHabitIds)
                               ->where('status', 'completed')
                               ->where('completion_value', '>=', 1.0)
                               ->orderBy('date', 'asc')
                               ->get();

        return $this->calculateAdvancedStreaks($completedLogs)['longest'];
    }

    private function getHabitAverageCompletionTime($habit)
    {
        $completedLogs = $habit->habitLogs()->whereNotNull('time_completed')->get();
        if ($completedLogs->isEmpty()) return null;

        $totalMinutes = $completedLogs->sum(function($log) {
            return Carbon::parse($log->time_completed)->diffInMinutes(Carbon::parse('00:00'));
        });

        return round($totalMinutes / $completedLogs->count());
    }

    private function calculateMoodAnalytics($user)
    {
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        $logs = HabitLog::whereIn('habit_id', $userHabitIds)
                       ->whereNotNull('mood')
                       ->get();

        return [
            'mood_distribution' => $logs->groupBy('mood')->map->count(),
            'average_mood' => $this->calculateAverageMoodValue($logs),
            'mood_by_habit' => $this->calculateMoodByHabit($logs),
            'mood_trends' => $this->calculateMoodTrends($logs)
        ];
    }

    private function calculateCompletionInsights($user)
    {
        $userHabitIds = Habits::where('user_id', $user->id)->pluck('id');
        $logs = HabitLog::whereIn('habit_id', $userHabitIds)->get();

        return [
            'best_time_to_complete' => $this->getBestCompletionTime($logs),
            'most_consistent_habit' => $this->getMostConsistentHabit($user),
            'completion_patterns' => $this->analyzeCompletionPatterns($logs)
        ];
    }

    private function calculateAverageMoodValue($logs)
    {
        $moodValues = [
            'terrible' => 1,
            'bad' => 2,
            'neutral' => 3,
            'good' => 4,
            'excellent' => 5
        ];

        $average = $logs->map(function($log) use ($moodValues) {
            return $moodValues[$log->mood] ?? 3;
        })->avg();

        return round($average, 1);
    }

    private function calculateMoodByHabit($logs)
    {
        return $logs->groupBy('habit_id')->map(function($habitLogs) {
            return [
                'habit_name' => $habitLogs->first()->habit->name,
                'average_mood' => $this->calculateAverageMoodValue($habitLogs),
                'total_logs' => $habitLogs->count()
            ];
        })->values();
    }

    private function calculateMoodTrends($logs)
    {
        $weeklyMoods = $logs->groupBy(function($log) {
            return $log->date->format('Y-W');
        })->map(function($weekLogs) {
            return $this->calculateAverageMoodValue($weekLogs);
        });

        return $weeklyMoods;
    }

    private function getBestCompletionTime($logs)
    {
        $logsWithTime = $logs->whereNotNull('time_completed');
        if ($logsWithTime->isEmpty()) return 'No data';

        $completionByHour = $logsWithTime->groupBy(function($log) {
            return Carbon::parse($log->time_completed)->hour;
        })->sortByDesc(function($group) {
            return $group->count();
        });

        $bestHour = $completionByHour->keys()->first();
        
        return $bestHour ? sprintf("%02d:00", $bestHour) : 'No data';
    }

    private function getMostConsistentHabit($user)
    {
        $habits = Habits::where('user_id', $user->id)
                       ->withCount(['habitLogs as completed_logs' => function($query) {
                           $query->where('status', 'completed');
                       }])
                       ->withCount('habitLogs as total_logs')
                       ->get()
                       ->map(function($habit) {
                           $consistency = $habit->total_logs > 0 ? 
                               ($habit->completed_logs / $habit->total_logs) * 100 : 0;
                           
                           return [
                               'habit' => $habit->name,
                               'consistency_rate' => round($consistency),
                               'current_streak' => $habit->current_streak
                           ];
                       })
                       ->sortByDesc('consistency_rate')
                       ->first();

        return $habits;
    }

    private function analyzeCompletionPatterns($logs)
    {
        $completionByDay = $logs->where('status', 'completed')
                               ->groupBy(function($log) {
                                   return $log->date->format('l');
                               })
                               ->map->count();

        $completionByMonth = $logs->where('status', 'completed')
                                 ->groupBy(function($log) {
                                     return $log->date->format('F');
                                 })
                                 ->map->count();

        return [
            'by_day_of_week' => $completionByDay,
            'by_month' => $completionByMonth,
            'most_productive_day' => $completionByDay->sortDesc()->keys()->first() ?? 'No data',
            'most_productive_month' => $completionByMonth->sortDesc()->keys()->first() ?? 'No data'
        ];
    }
}