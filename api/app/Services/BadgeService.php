<?php

namespace App\Services;

use App\Models\User;
use App\Models\Badge;
use App\Models\HabitLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    /**
     * Check and award badges untuk user
     */
    public function checkAndAwardBadges(User $user)
    {
        $badges = Badge::all();
        $awardedBadges = [];

        foreach ($badges as $badge) {
            if (!$user->badges()->where('badge_id', $badge->id)->exists()) {
                if ($this->meetsRequirement($user, $badge)) {
                    $this->awardBadge($user, $badge);
                    $awardedBadges[] = $badge;
                }
            }
        }

        return $awardedBadges;
    }

    /**
     * Calculate progress percentage for a badge
     */
    public function calculateProgress(User $user, Badge $badge): int
    {
        if ($user->badges()->where('badge_id', $badge->id)->exists()) {
            return 100;
        }

        switch ($badge->requirement_type) {
            // 📊 CONSISTENCY BADGES
            case 'morning_completions':
                return $this->calculateMorningCompletionsProgress($user, $badge->requirement_target);
            
            case 'night_completions':
                return $this->calculateNightCompletionsProgress($user, $badge->requirement_target);
            
            case 'perfect_weekends':
                return $this->calculatePerfectWeekendsProgress($user, $badge->requirement_target);
            
            case 'perfect_week':
                return $this->calculatePerfectWeekProgress($user);
            
            case 'perfect_month':
                return $this->calculatePerfectMonthProgress($user);
            
            case 'success_rate':
                return $this->calculateSuccessRateProgress($user, $badge->requirement_target);

            // 🎓 MASTERY BADGES
            case 'good_habit_completions':
                return $this->calculateGoodHabitCompletionsProgress($user, $badge->requirement_target);
            
            case 'bad_habit_avoided':
                return $this->calculateBadHabitAvoidedProgress($user, $badge->requirement_target);
            
            case 'bad_habit_streak':
                return $this->calculateBadHabitStreakProgress($user, $badge->requirement_target);
            
            case 'level':
                return $this->calculateLevelProgress($user, $badge->requirement_target);
            
            case 'morning_routine_completions':
                return $this->calculateMorningRoutineCompletionsProgress($user, $badge->requirement_target);

            // 🌈 VARIETY BADGES
            case 'unique_habits':
                return $this->calculateUniqueHabitsProgress($user, $badge->requirement_target);
            
            case 'balanced_habits':
                return $this->calculateBalancedHabitsProgress($user, $badge->requirement_target);

            // 🎉 SPECIAL BADGES
            case 'comeback':
                return $this->calculateComebackProgress($user);
            
            case 'resilient_comeback':
                return $this->calculateResilientComebackProgress($user);
            
            case 'first_completion':
                return $this->calculateFirstCompletionProgress($user);
            
            case 'first_week':
                return $this->calculateFirstWeekProgress($user);
            
            case 'app_usage_days':
                return $this->calculateAppUsageDaysProgress($user, $badge->requirement_target);

            default:
                return 0;
        }
    }

    /**
     * Check requirement for a specific badge (public method untuk claim)
     */
    public function checkRequirement(User $user, Badge $badge): bool
    {
        return $this->meetsRequirement($user, $badge);
    }

    /**
     * Check jika user memenuhi requirement badge
     */
    private function meetsRequirement(User $user, Badge $badge): bool
    {
        switch ($badge->requirement_type) {
            // 📊 CONSISTENCY BADGES
            case 'morning_completions':
                return $this->checkMorningCompletions($user, $badge->requirement_target);
            
            case 'night_completions':
                return $this->checkNightCompletions($user, $badge->requirement_target);
            
            case 'perfect_weekends':
                return $this->checkPerfectWeekends($user, $badge->requirement_target);
            
            case 'perfect_week':
                return $this->checkPerfectWeek($user);
            
            case 'perfect_month':
                return $this->checkPerfectMonth($user);
            
            case 'success_rate':
                return $this->checkSuccessRate($user, $badge->requirement_target);

            // 🎓 MASTERY BADGES
            case 'good_habit_completions':
                return $this->checkGoodHabitCompletions($user, $badge->requirement_target);
            
            case 'bad_habit_avoided':
                return $this->checkBadHabitAvoided($user, $badge->requirement_target);
            
            case 'bad_habit_streak':
                return $this->checkBadHabitStreak($user, $badge->requirement_target);
            
            case 'level':
                return $user->level >= $badge->requirement_target;
            
            case 'morning_routine_completions':
                return $this->checkMorningRoutineCompletions($user, $badge->requirement_target);

            // 🌈 VARIETY BADGES
            case 'unique_habits':
                return $this->checkUniqueHabits($user, $badge->requirement_target);
            
            case 'balanced_habits':
                return $this->checkBalancedHabits($user, $badge->requirement_target);

            // 🎉 SPECIAL BADGES
            case 'comeback':
                return $this->checkComeback($user);
            
            case 'resilient_comeback':
                return $this->checkResilientComeback($user);
            
            case 'first_completion':
                return $this->checkFirstCompletion($user);
            
            case 'first_week':
                return $this->checkFirstWeek($user);
            
            case 'app_usage_days':
                return $this->checkAppUsageDays($user, $badge->requirement_target);

            default:
                return false;
        }
    }

    // ==================== 📊 CONSISTENCY METHODS ====================

    private function checkMorningCompletions(User $user, int $target): bool
    {
        $completions = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'completed')
            ->whereTime('time_completed', '<', '08:00:00')
            ->count();

        return $completions >= $target;
    }

    private function calculateMorningCompletionsProgress(User $user, int $target): int
    {
        $completions = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'completed')
            ->whereTime('time_completed', '<', '08:00:00')
            ->count();

        return min(100, (int)($completions / $target * 100));
    }

    private function checkNightCompletions(User $user, int $target): bool
    {
        $completions = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'completed')
            ->whereTime('time_completed', '>', '22:00:00')
            ->count();

        return $completions >= $target;
    }

    private function calculateNightCompletionsProgress(User $user, int $target): int
    {
        $completions = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'completed')
            ->whereTime('time_completed', '>', '22:00:00')
            ->count();

        return min(100, (int)($completions / $target * 100));
    }

    private function checkPerfectWeekends(User $user, int $target): bool
    {
        $perfectWeekends = 0;
        $startDate = Carbon::now()->subMonths(3);
        
        $current = $startDate->copy();
        while ($current <= Carbon::now()) {
            if ($current->isWeekend()) {
                $saturday = $current->copy();
                $sunday = $current->copy()->addDay();
                
                if ($this->isPerfectDay($user, $saturday) && $this->isPerfectDay($user, $sunday)) {
                    $perfectWeekends++;
                }
                
                $current->addWeek();
            } else {
                $current->addDay();
            }
        }

        return $perfectWeekends >= $target;
    }

    private function calculatePerfectWeekendsProgress(User $user, int $target): int
    {
        $perfectWeekends = 0;
        $startDate = Carbon::now()->subMonths(3);
        
        $current = $startDate->copy();
        while ($current <= Carbon::now()) {
            if ($current->isWeekend()) {
                $saturday = $current->copy();
                $sunday = $current->copy()->addDay();
                
                if ($this->isPerfectDay($user, $saturday) && $this->isPerfectDay($user, $sunday)) {
                    $perfectWeekends++;
                }
                
                $current->addWeek();
            } else {
                $current->addDay();
            }
        }

        return min(100, (int)($perfectWeekends / $target * 100));
    }

    private function checkPerfectWeek(User $user): bool
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            if (!$this->isPerfectDay($user, $date)) {
                return false;
            }
        }
        
        return true;
    }

    private function calculatePerfectWeekProgress(User $user): int
    {
        // Untuk sekarang return 0 karena complex
        return 0;
    }

    private function checkPerfectMonth(User $user): bool
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        
        $current = $startOfMonth->copy();
        while ($current <= $endOfMonth) {
            if (!$this->isPerfectDay($user, $current)) {
                return false;
            }
            $current->addDay();
        }
        
        return true;
    }

    private function calculatePerfectMonthProgress(User $user): int
    {
        // Untuk sekarang return 0 karena complex
        return 0;
    }

    private function checkSuccessRate(User $user, int $targetRate): bool
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $totalLogs = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('date', '>=', $thirtyDaysAgo)
            ->count();

        $completedLogs = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('date', '>=', $thirtyDaysAgo)
            ->where('status', 'completed')
            ->count();

        if ($totalLogs === 0) return false;

        $successRate = ($completedLogs / $totalLogs) * 100;
        return $successRate >= $targetRate;
    }

    private function calculateSuccessRateProgress(User $user, int $targetRate): int
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $totalLogs = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('date', '>=', $thirtyDaysAgo)
            ->count();

        $completedLogs = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('date', '>=', $thirtyDaysAgo)
            ->where('status', 'completed')
            ->count();

        if ($totalLogs === 0) return 0;

        $successRate = ($completedLogs / $totalLogs) * 100;
        return min(100, (int)($successRate / $targetRate * 100));
    }

    // ==================== 🎓 MASTERY METHODS ====================

    private function checkGoodHabitCompletions(User $user, int $target): bool
    {
        $completions = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('type', 'good');
            })
            ->where('status', 'completed')
            ->count();

        return $completions >= $target;
    }

    private function calculateGoodHabitCompletionsProgress(User $user, int $target): int
    {
        $completions = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('type', 'good');
            })
            ->where('status', 'completed')
            ->count();

        return min(100, (int)($completions / $target * 100));
    }

    private function checkBadHabitAvoided(User $user, int $target): bool
    {
        $avoidedDays = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('type', 'bad');
            })
            ->where('status', '!=', 'completed')
            ->distinct('date')
            ->count('date');

        return $avoidedDays >= $target;
    }

    private function calculateBadHabitAvoidedProgress(User $user, int $target): int
    {
        $avoidedDays = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('type', 'bad');
            })
            ->where('status', '!=', 'completed')
            ->distinct('date')
            ->count('date');

        return min(100, (int)($avoidedDays / $target * 100));
    }

    private function checkBadHabitStreak(User $user, int $target): bool
    {
        $currentStreak = 0;
        $today = Carbon::today();
        
        for ($i = 0; $i < $target; $i++) {
            $checkDate = $today->copy()->subDays($i);
            
            $hasCompletedBadHabit = HabitLog::whereHas('habit', function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->where('type', 'bad');
                })
                ->whereDate('date', $checkDate)
                ->where('status', 'completed')
                ->exists();
            
            if ($hasCompletedBadHabit) {
                break;
            }
            
            $currentStreak++;
        }
        
        return $currentStreak >= $target;
    }

    private function calculateBadHabitStreakProgress(User $user, int $target): int
    {
        $currentStreak = 0;
        $today = Carbon::today();
        
        for ($i = 0; $i < $target; $i++) {
            $checkDate = $today->copy()->subDays($i);
            
            $hasCompletedBadHabit = HabitLog::whereHas('habit', function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->where('type', 'bad');
                })
                ->whereDate('date', $checkDate)
                ->where('status', 'completed')
                ->exists();
            
            if ($hasCompletedBadHabit) {
                break;
            }
            
            $currentStreak++;
        }
        
        return min(100, (int)($currentStreak / $target * 100));
    }

    private function checkMorningRoutineCompletions(User $user, int $target): bool
    {
        $completions = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('category', 'morning_routine');
            })
            ->where('status', 'completed')
            ->count();

        return $completions >= $target;
    }

    private function calculateMorningRoutineCompletionsProgress(User $user, int $target): int
    {
        $completions = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('category', 'morning_routine');
            })
            ->where('status', 'completed')
            ->count();

        return min(100, (int)($completions / $target * 100));
    }

    private function calculateLevelProgress(User $user, int $target): int
    {
        return min(100, (int)($user->level / $target * 100));
    }

    // ==================== 🌈 VARIETY METHODS ====================

    private function checkUniqueHabits(User $user, int $target): bool
    {
        $uniqueHabits = $user->habits()->count();
        return $uniqueHabits >= $target;
    }

    private function calculateUniqueHabitsProgress(User $user, int $target): int
    {
        $uniqueHabits = $user->habits()->count();
        return min(100, (int)($uniqueHabits / $target * 100));
    }

    private function checkBalancedHabits(User $user, int $target): bool
    {
        $goodHabits = $user->habits()->where('type', 'good')->count();
        $badHabits = $user->habits()->where('type', 'bad')->count();
        
        return ($goodHabits + $badHabits) >= $target && $goodHabits >= 5 && $badHabits >= 3;
    }

    private function calculateBalancedHabitsProgress(User $user, int $target): int
    {
        $goodHabits = $user->habits()->where('type', 'good')->count();
        $badHabits = $user->habits()->where('type', 'bad')->count();
        $totalHabits = $goodHabits + $badHabits;
        
        // Progress berdasarkan total habits
        $totalProgress = min(100, (int)($totalHabits / $target * 100));
        
        // Progress berdasarkan balance (good habits >= 5, bad habits >= 3)
        $goodProgress = min(100, (int)($goodHabits / 5 * 100));
        $badProgress = min(100, (int)($badHabits / 3 * 100));
        
        // Ambil progress terendah dari ketiga requirement
        return min($totalProgress, $goodProgress, $badProgress);
    }

    // ==================== 🎉 SPECIAL METHODS ====================

    private function checkComeback(User $user): bool
    {
        $lastCompletion = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orderBy('date', 'desc')
            ->first();

        if (!$lastCompletion) return false;

        $lastCompletionDate = Carbon::parse($lastCompletion->date);
        $breakDays = Carbon::now()->diffInDays($lastCompletionDate);
        
        return $breakDays >= 3;
    }

    private function calculateComebackProgress(User $user): int
    {
        $lastCompletion = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orderBy('date', 'desc')
            ->first();

        if (!$lastCompletion) return 0;

        $lastCompletionDate = Carbon::parse($lastCompletion->date);
        $breakDays = Carbon::now()->diffInDays($lastCompletionDate);
        
        // Progress berdasarkan break days (butuh 3 hari break)
        return min(100, (int)($breakDays / 3 * 100));
    }

    private function checkResilientComeback(User $user): bool
    {
        return false; // Placeholder
    }

    private function calculateResilientComebackProgress(User $user): int
    {
        return 0; // Placeholder
    }

    private function checkFirstCompletion(User $user): bool
    {
        $firstCompletion = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'completed')
            ->orderBy('date', 'asc')
            ->first();

        return !is_null($firstCompletion);
    }

    private function calculateFirstCompletionProgress(User $user): int
    {
        $firstCompletion = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'completed')
            ->orderBy('date', 'asc')
            ->first();

        return $firstCompletion ? 100 : 0;
    }

    private function checkFirstWeek(User $user): bool
    {
        $firstCompletion = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'completed')
            ->orderBy('date', 'asc')
            ->first();

        if (!$firstCompletion) return false;

        $firstWeekEnd = Carbon::parse($firstCompletion->date)->addWeek();
        $completionsInFirstWeek = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'completed')
            ->whereBetween('date', [
                $firstCompletion->date,
                $firstWeekEnd
            ])
            ->count();

        return $completionsInFirstWeek >= 5;
    }

    private function calculateFirstWeekProgress(User $user): int
    {
        $firstCompletion = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'completed')
            ->orderBy('date', 'asc')
            ->first();

        if (!$firstCompletion) return 0;

        $firstWeekEnd = Carbon::parse($firstCompletion->date)->addWeek();
        $completionsInFirstWeek = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'completed')
            ->whereBetween('date', [
                $firstCompletion->date,
                $firstWeekEnd
            ])
            ->count();

        return min(100, (int)($completionsInFirstWeek / 5 * 100));
    }

    private function checkAppUsageDays(User $user, int $target): bool
    {
        $usageDays = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->distinct('date')
            ->count('date');

        return $usageDays >= $target;
    }

    private function calculateAppUsageDaysProgress(User $user, int $target): int
    {
        $usageDays = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->distinct('date')
            ->count('date');

        return min(100, (int)($usageDays / $target * 100));
    }

    // ==================== HELPER METHODS ====================

    private function isPerfectDay(User $user, Carbon $date): bool
    {
        $activeHabits = $user->habits()
            ->where('is_active', true)
            ->where(function($query) use ($date) {
                $query->whereNull('start_date')
                      ->orWhere('start_date', '<=', $date->format('Y-m-d'));
            })
            ->where(function($query) use ($date) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $date->format('Y-m-d'));
            })
            ->count();

        if ($activeHabits === 0) return false;

        $completions = HabitLog::whereHas('habit', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereDate('date', $date)
            ->where('status', 'completed')
            ->count();

        return $completions === $activeHabits;
    }

    private function awardBadge(User $user, Badge $badge)
    {
        $user->badges()->attach($badge->id, ['unlocked_at' => now()]);
        
        // Award XP dan coins
        $user->increment('current_xp', $badge->reward_xp);
        $user->increment('coins', $badge->reward_coins);
        $user->save();
    }
}