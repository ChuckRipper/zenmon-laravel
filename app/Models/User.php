<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// /**
//  * @OA\Schema(
//  *      schema="User",
//  *      type="object",
//  *      title="User",
//  *      description="System user",
//  *      @OA\Property(property="id", type="integer", example=1),
//  *      @OA\Property(property="login", type="string", example="admin"),
//  *      @OA\Property(property="email", type="string", example="admin@zenmon.local"),
//  *      @OA\Property(property="first_name", type="string", example="John"),
//  *      @OA\Property(property="last_name", type="string", example="Doe"),
//  *      @OA\Property(property="role", type="string", enum={"Administrator", "Agent", "User"}, example="Administrator"),
//  *      @OA\Property(property="is_active", type="boolean", example=true),
//  *      @OA\Property(property="created_at", type="string", format="date-time"),
//  *      @OA\Property(property="updated_at", type="string", format="date-time"),
//  *      @OA\Property(property="last_login_date", type="string", format="date-time", nullable=true)
//  * )
//  */
/// <summary>
/// User model representing system users with role-based access control
/// Supports three roles: Administrator, Agent, User
/// </summary>
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    #region Properties
    /// <summary>
    /// The attributes that are mass assignable.
    /// </summary>
    /// <var>array<int, string></var>
    protected $fillable = [
        'login',
        'email',
        'password',
        'first_name',    
        'last_name',     
        'role',          
        'is_active',     
        'last_login_date'
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

    /// <summary>
    /// Available user roles in the system
    /// </summary>
    public const ROLES = [
        'Administrator' => 'Administrator',
        'Agent' => 'Agent', 
        'User' => 'User'
    ];
    #endregion

    #region Relationships
    /// <summary>
    /// Get hosts managed by this user
    /// </summary>
    /// <returns>HasMany</returns>
    public function createdAlertThresholds()
    {
        return $this->hasMany(AlertThreshold::class, 'created_by_user_id', 'id');
    }

    /// <summary>
    /// Get alerts acknowledged by this user
    /// </summary>
    /// <returns>HasMany</returns>
    public function acknowledgedAlerts()
    {
        return $this->hasMany(Alert::class, 'acknowledged_by_user_id', 'id');
    }

    /// <summary>
    /// Get alerts closed by this user
    /// </summary>
    /// <returns>HasMany</returns>
    public function closedAlerts()
    {
        return $this->hasMany(Alert::class, 'closed_by_user_id', 'id');
    }

    /// <summary>
    /// Get user sessions
    /// </summary>
    /// <returns>HasMany</returns>
    public function userSessions()
    {
        return $this->hasMany(UserSession::class, 'user_id', 'id');
    }

    /// <summary>
    /// Get hosts managed by this user
    /// </summary>
    /// <returns>HasMany</returns>
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
    /// Check if user is agent
    /// </summary>
    /// <returns>bool</returns>
    public function isAgent(): bool
    {
        return $this->role === 'Agent';
    }

    /// <summary>
    /// Check if user is regular user
    /// </summary>
    /// <returns>bool</returns>
    public function isUser(): bool
    {
        return $this->role === 'User';
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
    /// Check if user has specific role
    /// </summary>
    /// <param>string $role</param>
    /// <returns>bool</returns>
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /// <summary>
    /// Check if user can access administrative features
    /// </summary>
    /// <returns>bool</returns>
    public function canAccessAdmin(): bool
    {
        return $this->isAdministrator() && $this->is_active;
    }

    /// <summary>
    /// Check if user can manage other users
    /// </summary>
    /// <returns>bool</returns>
    public function canManageUsers(): bool
    {
        return $this->isAdministrator() && $this->is_active;
    }

    /// <summary>
    /// Check if user can manage hosts and configurations
    /// </summary>
    /// <returns>bool</returns>
    public function canManageHosts(): bool
    {
        return $this->isAdministrator() && $this->is_active;
    }

    /// <summary>
    /// Check if user can view monitoring data
    /// </summary>
    /// <returns>bool</returns>
    public function canViewMonitoringData(): bool
    {
        return $this->is_active && in_array($this->role, ['Administrator', 'User']);
    }

    /// <summary>
    /// Check if user can submit metrics (agent endpoint access)
    /// </summary>
    /// <returns>bool</returns>
    public function canSubmitMetrics(): bool
    {
        return $this->isAgent() && $this->is_active;
    }

    /// <summary>
    /// Get role-specific permissions
    /// </summary>
    /// <returns>array</returns>
    public function getPermissions(): array
    {
        return match($this->role) {
            'Administrator' => [
                'manage_users', 'manage_hosts', 'configure_alerts', 
                'view_metrics', 'manage_api', 'system_admin'
            ],
            'Agent' => [
                'send_metrics', 'heartbeat', 'agent_endpoints'
            ],
            'User' => [
                'view_metrics', 'view_hosts', 'view_alerts', 'acknowledge_alerts'
            ],
            default => []
        };
    }

    /// <summary>
    /// Check if user has elevated privileges (admin or agent)
    /// </summary>
    /// <returns>bool</returns>
    public function hasElevatedPrivileges(): bool
    {
        return in_array($this->role, ['Administrator', 'Agent']);
    }

    /// <summary>
    /// Get full name
    /// </summary>
    /// <returns>string</returns>
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /// <summary>
    /// Update last login timestamp
    /// </summary>
    /// <returns>void</returns>
    public function updateLastLogin(): void
    {
        $this->update(['last_login_date' => now()]);
    }

    /// <summary>
    /// Scope to filter active users
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /// <summary>
    /// Scope to filter by role
    /// </summary>
    /// <param>Builder $query</param>
    /// <param>string $role</param>
    /// <returns>Builder</returns>
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
    #endregion
}