<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HabitLog extends Model
{
    use HasFactory;

    protected $table = 'habit_log';
    protected $fillable = [
        'habit_id',
        'user_id',
        'date',
        'status',
        'completion_value',
        'notes',
        'xp_earned',
        'streak_bonus',
        'time_completed',
        'mood',
        'difficulty_rating'
    ];

    protected $casts = [
        'date' => 'date',
        'completion_value' => 'decimal:2',
        'xp_earned' => 'integer',
        'streak_bonus' => 'integer',
        'difficulty_rating' => 'integer'
    ];

    protected $attributes = [
        'completion_value' => 0.00,
        'xp_earned' => 0,
        'streak_bonus' => 0,
        'difficulty_rating' => 1
    ];

    // Relasi
    public function habit()
    {
        return $this->belongsTo(Habits::class, 'habit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scope untuk logs yang completed
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Scope untuk logs berdasarkan tanggal
    public function scopeByDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    // Method untuk mengecek apakah log considered completed
    public function isFullyCompleted()
    {
        return $this->status === 'completed' && $this->completion_value >= 1.0;
    }

    // Method untuk mengecek partial completion
    public function isPartiallyCompleted()
    {
        return $this->status === 'completed' && $this->completion_value > 0 && $this->completion_value < 1.0;
    }
}