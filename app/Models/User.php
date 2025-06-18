<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    #region Properties
    /// <summary>
    /// The attributes that are mass assignable.
    /// </summary>
    /// <var>array<int, string></var>
    protected $fillable = [
        'login',           // Zmienione z 'name' zgodnie z UML
        'email',
        'password',
        'first_name',      // Nowe pole z UML
        'last_name',       // Nowe pole z UML
        'role',            // Nowe pole z UML
        'is_active',       // Nowe pole z UML
        'last_login_date'  // Nowe pole z UML
    ];

    /// <summary>
    /// The attributes that should be hidden for serialization.
    /// </summary>
    /// <var>array<int, string></var>
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /// <summary>
    /// Get the attributes that should be cast.
    /// </summary>
    /// <returns>array<string, string></returns>
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_date' => 'datetime'
        ];
    }
    #endregion

    #region Relationships
    public function createdAlertThresholds()
    {
        return $this->hasMany(AlertThreshold::class, 'created_by_user_id', 'id');
    }

    public function acknowledgedAlerts()
    {
        return $this->hasMany(Alert::class, 'acknowledged_by_user_id', 'id');
    }

    public function closedAlerts()
    {
        return $this->hasMany(Alert::class, 'closed_by_user_id', 'id');
    }

    public function userSessions()
    {
        return $this->hasMany(UserSession::class, 'user_id', 'id');
    }

    public function hostConfigurations()
    {
        return $this->hasMany(HostConfiguration::class, 'updated_by_user_id', 'id');
    }
    #endregion

    #region Methods
    /// <summary>
    /// Check if user is administrator
    /// </summary>
    /// <returns>bool</returns>
    public function isAdministrator(): bool
    {
        return $this->role === 'Administrator';
    }

    /// <summary>
    /// Check if user is active
    /// </summary>
    /// <returns>bool</returns>
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /// <summary>
    /// Get full name
    /// </summary>
    /// <returns>string</returns>
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    #endregion
}
