<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function habits()
    {
        return $this->hasMany(Habits::class);
    }

    public function habitLogs()
    {
        return $this->hasMany(HabitLog::class);
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'badge_user')
                    ->withPivot('unlocked_at')
                    ->withTimestamps();
    }

    public function getTotalBadgesAttribute()
    {
        return $this->badges()->count();
    }
    
    public function getNextLevelXpAttribute()
    {
        return $this->level * 100; // contoh: level 1 butuh 100 XP, level 2 butuh 200 XP, dst
    }
}
