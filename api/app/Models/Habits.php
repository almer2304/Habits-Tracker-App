<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Habits extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'description',
        'target_frequency',
        'target_count',
        'color',
        'icon',
        'is_active',
        'reminder_time',
        'start_date',
        'end_date',
        'current_streak',
        'longest_streak',
        'total_completions',
        'base_xp',
        'difficulty',
        'category',
        'privacy'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reminder_time' => 'datetime',
        'is_active' => 'boolean',
        'target_count' => 'integer',
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'total_completions' => 'integer',
        'base_xp' => 'integer'
    ];

    protected $attributes = [
        'target_frequency' => 'daily',
        'target_count' => 1,
        'color' => '#3B82F6',
        'icon' => '📝',
        'is_active' => true,
        'difficulty' => 'medium',
        'privacy' => 'private',
        'current_streak' => 0,
        'longest_streak' => 0,
        'total_completions' => 0,
        'base_xp' => 10
    ];

    // Relasi
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function habitLogs()
    {
        return $this->hasMany(HabitLog::class, 'habit_id');
    }

    // Scope untuk habit aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope untuk habit berdasarkan kategori
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // Method untuk mengecek apakah habit masih berjalan
    public function isActive()
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->end_date && now()->gt($this->end_date)) {
            return false;
        }

        return now()->gte($this->start_date);
    }
}