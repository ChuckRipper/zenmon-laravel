<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="User",
 *      type="object",
 *      title="User",
 *      description="System user",
 *      @OA\Property(property="id", type="integer", example=1),
 *      @OA\Property(property="login", type="string", example="admin"),
 *      @OA\Property(property="email", type="string", example="admin@zenmon.local"),
 *      @OA\Property(property="first_name", type="string", example="John"),
 *      @OA\Property(property="last_name", type="string", example="Doe"),
 *      @OA\Property(property="full_name", type="string", example="John Doe"),
 *      @OA\Property(property="role", type="string", enum={"Administrator", "Agent", "User"}, example="Administrator"),
 *      @OA\Property(property="is_active", type="boolean", example=true),
 *      @OA\Property(property="last_login_date", type="string", format="date-time", nullable=true),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(property="account_age_days", type="integer", example=45),
 *      @OA\Property(property="role_badge", type="string", example="admin"),
 *      @OA\Property(property="status_indicator", type="string", enum={"active", "inactive"}, example="active"),
 *      @OA\Property(
 *          property="permissions",
 *          type="object",
 *          @OA\Property(property="can_create_users", type="boolean", example=true),
 *          @OA\Property(property="can_manage_hosts", type="boolean", example=true),
 *          @OA\Property(property="can_view_alerts", type="boolean", example=true),
 *          @OA\Property(property="can_manage_thresholds", type="boolean", example=true)
 *      )
 * )
 */
/// <summary>
/// Resource for transforming User model data for API responses
/// Provides formatted user information with computed properties
/// </summary>
class UserResource extends JsonResource
{
    #region Properties

    /// <summary>
    /// Additional data to include when transforming the resource
    /// </summary>
    public static $wrap = null;

    #endregion

    #region Methods

    /// <summary>
    /// Transform the resource into an array
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>array<string, mixed></returns>
    public function toArray(Request $request): array
    {
        return [
            // Basic user information
            'id' => $this->id,
            'login' => $this->login,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->getFullName(),
            'role' => $this->role,
            'is_active' => (bool) $this->is_active,
            
            // Timestamps
            'last_login_date' => $this->last_login_date?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Computed properties
            'account_age_days' => $this->getAccountAgeDays(),
            'role_badge' => $this->getRoleBadge(),
            'status_indicator' => $this->getStatusIndicator(),
            'display_name' => $this->getDisplayName(),
            
            // Permission information
            'permissions' => [
                'can_create_users' => $this->canCreateUsers(),
                'can_manage_hosts' => $this->canManageHosts(),
                'can_view_alerts' => $this->canViewAlerts(),
                'can_manage_thresholds' => $this->canManageThresholds(),
                'can_access_api_docs' => $this->canAccessApiDocs(),
                'can_receive_notifications' => $this->canReceiveNotifications()
            ],
            
            // Security information (only for own profile)
            'security_info' => $this->when(
                $this->isOwnProfile($request),
                fn() => [
                    'has_recent_login' => $this->hasRecentLogin(),
                    'password_age_days' => $this->getPasswordAgeDays(),
                    'requires_password_change' => $this->requiresPasswordChange()
                ]
            )
        ];
    }

    /// <summary>
    /// Get user's full name
    /// </summary>
    /// <returns>string</returns>
    private function getFullName(): string
    {
        $firstName = $this->first_name ?? '';
        $lastName = $this->last_name ?? '';
        return trim($firstName . ' ' . $lastName);
    }

    /// <summary>
    /// Get display name for UI (full name or login as fallback)
    /// </summary>
    /// <returns>string</returns>
    private function getDisplayName(): string
    {
        $fullName = $this->getFullName();
        return !empty($fullName) ? $fullName : ($this->login ?? 'Unknown');
    }

    /// <summary>
    /// Get account age in days
    /// </summary>
    /// <returns>int</returns>
    private function getAccountAgeDays(): int
    {
        return $this->created_at ? $this->created_at->diffInDays(now()) : 0;
    }

    /// <summary>
    /// Get role badge identifier for UI
    /// </summary>
    /// <returns>string</returns>
    private function getRoleBadge(): string
    {
        return match($this->role) {
            'Administrator' => 'admin',
            'Agent' => 'agent',
            'User' => 'user',
            default => 'unknown'
        };
    }

    /// <summary>
    /// Get status indicator for UI
    /// </summary>
    /// <returns>string</returns>
    private function getStatusIndicator(): string
    {
        return $this->is_active ? 'active' : 'inactive';
    }

    /// <summary>
    /// Check if this is user's own profile
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>bool</returns>
    private function isOwnProfile(Request $request): bool
    {
        $currentUser = $request->user();
        return $currentUser && $currentUser->id === $this->id;
    }

    #endregion

    #region Permission Methods

    /// <summary>
    /// Check if user can create other users
    /// </summary>
    /// <returns>bool</returns>
    private function canCreateUsers(): bool
    {
        return $this->role === 'Administrator';
    }

    /// <summary>
    /// Check if user can manage hosts
    /// </summary>
    /// <returns>bool</returns>
    private function canManageHosts(): bool
    {
        return $this->role === 'Administrator';
    }

    /// <summary>
    /// Check if user can view alerts
    /// </summary>
    /// <returns>bool</returns>
    private function canViewAlerts(): bool
    {
        return in_array($this->role, ['Administrator', 'User']);
    }

    /// <summary>
    /// Check if user can manage alert thresholds
    /// </summary>
    /// <returns>bool</returns>
    private function canManageThresholds(): bool
    {
        return $this->role === 'Administrator';
    }

    /// <summary>
    /// Check if user can access API documentation
    /// </summary>
    /// <returns>bool</returns>
    private function canAccessApiDocs(): bool
    {
        return $this->role === 'Administrator';
    }

    /// <summary>
    /// Check if user can receive notifications
    /// </summary>
    /// <returns>bool</returns>
    private function canReceiveNotifications(): bool
    {
        return $this->is_active && !empty($this->email);
    }

    #endregion

    #region Security Methods

    /// <summary>
    /// Check if user has logged in recently (within 24 hours)
    /// </summary>
    /// <returns>bool</returns>
    private function hasRecentLogin(): bool
    {
        if (!$this->last_login_date) {
            return false;
        }
        
        return $this->last_login_date->isAfter(now()->subDay());
    }

    /// <summary>
    /// Get password age in days (placeholder - would need password_updated_at field)
    /// </summary>
    /// <returns>int</returns>
    private function getPasswordAgeDays(): int
    {
        // Placeholder - would typically track password_updated_at
        return $this->updated_at ? $this->updated_at->diffInDays(now()) : 0;
    }

    /// <summary>
    /// Check if user requires password change
    /// </summary>
    /// <returns>bool</returns>
    private function requiresPasswordChange(): bool
    {
        // Placeholder logic - could implement password expiry rules
        $passwordAgeDays = $this->getPasswordAgeDays();
        return $passwordAgeDays > 90; // Password older than 90 days
    }

    #endregion

    #region Response Customization

    /// <summary>
    /// Add custom headers to the response
    /// </summary>
    /// <param>Request $request</param>
    /// <param>$response</param>
    /// <returns>void</returns>
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'User');
        $response->header('X-User-Role', $this->role);
    }

    #endregion
}