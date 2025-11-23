<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\BadgeService;

class BadgeController extends Controller
{
    protected $badgeService;

    public function __construct(BadgeService $badgeService)
    {
        $this->badgeService = $badgeService;
    }

    public function index()
    {
        try {
            $badges = Badge::all();
            
            return response()->json([
                'badges' => $badges,
                'total' => $badges->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch badges',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $badge = Badge::findOrFail($id);
            
            return response()->json([
                'badge' => $badge
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Badge not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function userBadges(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Get badges with pivot data (unlocked_at)
            $badges = $user->badges()->withPivot('unlocked_at')->get();
            
            return response()->json([
                'badges' => $badges,
                'total_earned' => $badges->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch user badges',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllBadgesWithUserProgress(Request $request)
    {
        try {
            $user = Auth::user();
            $allBadges = Badge::all();
            
            // Get user's earned badges with pivot data
            $userBadges = $user->badges()->withPivot('unlocked_at')->get();
            
            // Combine all badges with user progress
            $badgesWithProgress = $allBadges->map(function ($badge) use ($userBadges, $user) {
                $userBadge = $userBadges->where('id', $badge->id)->first();
                $isEarned = !is_null($userBadge);
                
                return [
                    'id' => $badge->id,
                    'name' => $badge->name,
                    'description' => $badge->description,
                    'icon' => $badge->icon,
                    'category' => $badge->category,
                    'requirement_type' => $badge->requirement_type,
                    'requirement_target' => $badge->requirement_target,
                    'habit_type' => $badge->habit_type,
                    'reward_xp' => $badge->reward_xp,
                    'reward_coins' => $badge->reward_coins,
                    'is_earned' => $isEarned,
                    'unlocked_at' => $isEarned ? $userBadge->pivot->unlocked_at : null,
                    'progress' => $isEarned ? 100 : $this->calculateBadgeProgress($user, $badge),
                ];
            });
            
            return response()->json([
                'badges' => $badgesWithProgress,
                'total_badges' => $allBadges->count(),
                'earned_badges' => $userBadges->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch badges with progress',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Check dan award badges untuk user saat ini
     */
    public function checkBadges(Request $request)
    {
        try {
            $user = Auth::user();
            $awardedBadges = $this->badgeService->checkAndAwardBadges($user);

            return response()->json([
                'message' => 'Badge check completed',
                'awarded_badges' => $awardedBadges,
                'total_awarded' => count($awardedBadges)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check badges',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check progress untuk badge tertentu
     */
    public function getBadgeProgress($badgeId)
    {
        try {
            $user = Auth::user();
            $badge = Badge::findOrFail($badgeId);
            
            $progress = $this->calculateBadgeProgress($user, $badge);
            $isEarned = $user->badges()->where('badge_id', $badgeId)->exists();

            return response()->json([
                'badge' => $badge,
                'progress' => $progress,
                'is_earned' => $isEarned
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get badge progress',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function claimBadge(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $badge = Badge::findOrFail($id);
            
            // Check if user already has the badge
            if ($user->badges()->where('badge_id', $id)->exists()) {
                return response()->json([
                    'message' => 'Badge already claimed'
                ], 400);
            }
            
            // Check if user meets requirement
            if (!$this->badgeService->checkRequirement($user, $badge)) {
                return response()->json([
                    'message' => 'Requirement not met for this badge'
                ], 400);
            }
            
            // Attach badge to user with unlocked_at timestamp
            $user->badges()->attach($id, ['unlocked_at' => now()]);
            
            // Award XP and coins
            $user->increment('current_xp', $badge->reward_xp);
            $user->increment('coins', $badge->reward_coins);
            $user->save();
            
            return response()->json([
                'message' => 'Badge claimed successfully!',
                'badge' => $badge,
                'reward_xp' => $badge->reward_xp,
                'reward_coins' => $badge->reward_coins
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to claim badge',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate progress for a specific badge
     */
    private function calculateBadgeProgress(User $user, Badge $badge)
    {
        // Delegate progress calculation to BadgeService
        return $this->badgeService->calculateProgress($user, $badge);
    }
}