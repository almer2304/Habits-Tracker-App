<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description', 
        'icon',
        'category',
        'requirement_type',
        'requirement_target',
        'habit_type',
        'reward_xp',
        'reward_coins'
    ];

    /**
     * The users that belong to the badge.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'badge_user')
                    ->withPivot('unlocked_at')
                    ->withTimestamps();
    }
}