<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    use HasFactory;

    #region Properties
    protected $primaryKey = 'session_id';
    
    protected $fillable = [
        'user_id',
        'session_token',
        'login_date',
        'last_activity_date',
        'ip_address',
        'is_active'
    ];

    protected $casts = [
        'login_date' => 'datetime',
        'last_activity_date' => 'datetime',
        'is_active' => 'boolean'
    ];

    public $timestamps = false; // Używamy własnych login_date, last_activity_date
    #endregion

    #region Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    #endregion

    #region Scopes
    /// <summary>
    /// Scope for active sessions
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /// <summary>
    /// Scope for expired sessions (inactive for N hours)
    /// </summary>
    /// <param>$query</param>
    /// <param>int $hours</param>
    /// <returns>mixed</returns>
    public function scopeExpired($query, int $hours = 8)
    {
        return $query->where('last_activity_date', '<', now()->subHours($hours));
    }

    /// <summary>
    /// Scope for recent sessions (last N hours)
    /// </summary>
    /// <param>$query</param>
    /// <param>int $hours</param>
    /// <returns>mixed</returns>
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('login_date', '>=', now()->subHours($hours));
    }
    #endregion

    #region Methods
    /// <summary>
    /// Check if session is expired (8 hours of inactivity)
    /// </summary>
    /// <returns>bool</returns>
    public function isExpired(): bool
    {
        return $this->last_activity_date < now()->subHours(8);
    }

    /// <summary>
    /// Update last activity timestamp
    /// </summary>
    /// <returns>void</returns>
    public function updateActivity(): void
    {
        $this->update(['last_activity_date' => now()]);
    }

    /// <summary>
    /// Terminate session
    /// </summary>
    /// <returns>void</returns>
    public function terminate(): void
    {
        $this->update(['is_active' => false]);
    }

    /// <summary>
    /// Get session duration in minutes
    /// </summary>
    /// <returns>int</returns>
    public function getDurationInMinutes(): int
    {
        return $this->login_date->diffInMinutes($this->last_activity_date);
    }

    /// <summary>
    /// Get formatted session duration
    /// </summary>
    /// <returns>string</returns>
    public function getFormattedDuration(): string
    {
        $minutes = $this->getDurationInMinutes();
        
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return $hours . 'h ' . $remainingMinutes . 'm';
    }

    /// <summary>
    /// Check if session is from localhost
    /// </summary>
    /// <returns>bool</returns>
    public function isLocalhost(): bool
    {
        return in_array($this->ip_address, ['127.0.0.1', '::1', 'localhost']);
    }
    #endregion
}
