<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="UserResource",
 *      type="object",
 *      title="UserResource",
 *      description="User API Resource with computed fields",
 *      @OA\Property(property="id", type="integer", example=1),
 *      @OA\Property(property="login", type="string", example="admin"),
 *      @OA\Property(property="email", type="string", example="admin@zenmon.local"),
 *      @OA\Property(property="first_name", type="string", example="John"),
 *      @OA\Property(property="last_name", type="string", example="Doe"),
 *      @OA\Property(property="full_name", type="string", example="John Doe"),
 *      @OA\Property(property="role", type="string", enum={"Administrator", "User"}, example="Administrator"),
 *      @OA\Property(property="is_active", type="boolean", example=true),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(property="last_login_date", type="string", format="date-time", nullable=true),
 *      @OA\Property(
 *          property="statistics",
 *          type="object",
 *          @OA\Property(property="total_sessions", type="integer"),
 *          @OA\Property(property="active_sessions", type="integer"),
 *          @OA\Property(property="created_alert_thresholds", type="integer"),
 *          @OA\Property(property="acknowledged_alerts", type="integer"),
 *          @OA\Property(property="closed_alerts", type="integer"),
 *          @OA\Property(property="updated_configurations", type="integer")
 *      ),
 *      @OA\Property(
 *          property="computed_fields",
 *          type="object",
 *          @OA\Property(property="account_age_days", type="integer"),
 *          @OA\Property(property="last_login_ago", type="string", nullable=true),
 *          @OA\Property(property="activity_status", type="string"),
 *          @OA\Property(property="permission_level", type="string"),
 *          @OA\Property(property="session_activity", type="string"),
 *          @OA\Property(property="account_status", type="string"),
 *          @OA\Property(property="days_since_last_login", type="integer", nullable=true)
 *      ),
 *      @OA\Property(
 *          property="analysis",
 *          type="object",
 *          @OA\Property(property="user_engagement", type="string", enum={"very_active", "active", "moderate", "inactive", "dormant"}),
 *          @OA\Property(property="security_profile", type="string", enum={"secure", "standard", "needs_attention"}),
 *          @OA\Property(property="recommendations", type="array", @OA\Items(type="string")),
 *          @OA\Property(property="role_utilization", type="string"),
 *          @OA\Property(property="system_contribution", type="string")
 *      )
 * )
 */
class UserResource extends JsonResource
{
    #region Properties
    /// <summary>
    /// Additional data to include when transforming the resource
    /// </summary>
    public static $wrap = null;
    #endregion

    #region Methods

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    /// <summary>
    /// Transform user resource into array with computed fields and analysis
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>array</returns>
    public function toArray(Request $request): array
    {
        return [
            // Basic user information
            'id' => $this->id,
            'login' => $this->login,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->getFullNameAttribute(),
            'role' => $this->role,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'last_login_date' => $this->last_login_date,

            // Statistics (when relationships are loaded)
            'statistics' => [
                'total_sessions' => $this->when($this->relationLoaded('userSessions'), function () {
                    return $this->userSessions->count();
                }),
                'active_sessions' => $this->when($this->relationLoaded('userSessions'), function () {
                    return $this->userSessions->where('is_active', true)->count();
                }),
                'created_alert_thresholds' => $this->when($this->relationLoaded('createdAlertThresholds'), function () {
                    return $this->createdAlertThresholds->count();
                }),
                'acknowledged_alerts' => $this->when($this->relationLoaded('acknowledgedAlerts'), function () {
                    return $this->acknowledgedAlerts->count();
                }),
                'closed_alerts' => $this->when($this->relationLoaded('closedAlerts'), function () {
                    return $this->closedAlerts->count();
                }),
                'updated_configurations' => $this->when($this->relationLoaded('hostConfigurations'), function () {
                    return $this->hostConfigurations->count();
                })
            ],

            // Recent activity (when loaded)
            'recent_sessions' => $this->when($this->relationLoaded('userSessions'), function () {
                return $this->userSessions->sortByDesc('login_date')->take(5)->map(function ($session) {
                    return [
                        'session_id' => $session->session_id,
                        'login_date' => $session->login_date,
                        'last_activity_date' => $session->last_activity_date,
                        'is_active' => $session->is_active,
                        'ip_address' => $session->ip_address,
                        'duration_minutes' => $session->getDurationInMinutes()
                    ];
                });
            }),

            // Recent alerts activity (when loaded)
            'recent_alert_activity' => $this->when($this->relationLoaded('acknowledgedAlerts') || $this->relationLoaded('closedAlerts'), function () {
                $recentActivity = collect();
                
                if ($this->relationLoaded('acknowledgedAlerts')) {
                    $acknowledged = $this->acknowledgedAlerts->take(3)->map(function ($alert) {
                        return [
                            'type' => 'acknowledged',
                            'alert_id' => $alert->alert_id,
                            'date' => $alert->acknowledged_date,
                            'host_name' => $alert->host->host_name ?? 'Unknown'
                        ];
                    });
                    $recentActivity = $recentActivity->merge($acknowledged);
                }
                
                if ($this->relationLoaded('closedAlerts')) {
                    $closed = $this->closedAlerts->take(3)->map(function ($alert) {
                        return [
                            'type' => 'closed',
                            'alert_id' => $alert->alert_id,
                            'date' => $alert->closed_date,
                            'host_name' => $alert->host->host_name ?? 'Unknown'
                        ];
                    });
                    $recentActivity = $recentActivity->merge($closed);
                }
                
                return $recentActivity->sortByDesc('date')->take(5)->values();
            }),

            // Computed fields
            'computed_fields' => [
                'account_age_days' => $this->getAccountAgeDays(),
                'last_login_ago' => $this->getLastLoginAgo(),
                'activity_status' => $this->getActivityStatus(),
                'permission_level' => $this->getPermissionLevel(),
                'session_activity' => $this->getSessionActivity(),
                'account_status' => $this->getAccountStatus(),
                'days_since_last_login' => $this->getDaysSinceLastLogin(),
                'productivity_score' => $this->getProductivityScore()
            ],

            // Analysis and insights
            'analysis' => [
                'user_engagement' => $this->getUserEngagement(),
                'security_profile' => $this->getSecurityProfile(),
                'recommendations' => $this->getRecommendations(),
                'role_utilization' => $this->getRoleUtilization(),
                'system_contribution' => $this->getSystemContribution(),
                'account_health' => $this->getAccountHealth()
            ]
        ];
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get account age in days
    /// </summary>
    /// <returns>int</returns>
    private function getAccountAgeDays(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /// <summary>
    /// Get human readable time since last login
    /// </summary>
    /// <returns>string|null</returns>
    private function getLastLoginAgo(): ?string
    {
        if (!$this->last_login_date) {
            return null;
        }

        $days = $this->last_login_date->diffInDays(now());
        $hours = $this->last_login_date->diffInHours(now());
        $minutes = $this->last_login_date->diffInMinutes(now());

        if ($days > 0) {
            return $days . ' days ago';
        } elseif ($hours > 0) {
            return $hours . ' hours ago';
        } elseif ($minutes > 0) {
            return $minutes . ' minutes ago';
        } else {
            return 'just now';
        }
    }

    /// <summary>
    /// Get activity status
    /// </summary>
    /// <returns>string</returns>
    private function getActivityStatus(): string
    {
        if (!$this->is_active) {
            return 'disabled';
        }

        if (!$this->last_login_date) {
            return 'never_logged_in';
        }

        $daysSinceLogin = $this->getDaysSinceLastLogin();
        
        if ($daysSinceLogin <= 1) {
            return 'very_active';
        } elseif ($daysSinceLogin <= 7) {
            return 'active';
        } elseif ($daysSinceLogin <= 30) {
            return 'moderately_active';
        } elseif ($daysSinceLogin <= 90) {
            return 'inactive';
        } else {
            return 'dormant';
        }
    }

    /// <summary>
    /// Get permission level description
    /// </summary>
    /// <returns>string</returns>
    private function getPermissionLevel(): string
    {
        switch ($this->role) {
            case 'Administrator':
                return 'full_access';
            case 'User':
                return 'limited_access';
            default:
                return 'unknown';
        }
    }

    /// <summary>
    /// Get session activity assessment
    /// </summary>
    /// <returns>string</returns>
    private function getSessionActivity(): string
    {
        if (!$this->relationLoaded('userSessions')) {
            return 'unknown';
        }

        $activeSessions = $this->userSessions->where('is_active', true)->count();
        $recentSessions = $this->userSessions->where('login_date', '>=', now()->subDays(7))->count();

        if ($activeSessions > 3) {
            return 'very_high';
        } elseif ($activeSessions > 1) {
            return 'high';
        } elseif ($activeSessions == 1) {
            return 'normal';
        } elseif ($recentSessions > 0) {
            return 'low';
        } else {
            return 'none';
        }
    }

    /// <summary>
    /// Get account status
    /// </summary>
    /// <returns>string</returns>
    private function getAccountStatus(): string
    {
        if (!$this->is_active) {
            return 'suspended';
        }

        $daysSinceLogin = $this->getDaysSinceLastLogin();
        
        if ($daysSinceLogin === null) {
            return 'new';
        } elseif ($daysSinceLogin <= 30) {
            return 'active';
        } elseif ($daysSinceLogin <= 90) {
            return 'idle';
        } else {
            return 'stale';
        }
    }

    /// <summary>
    /// Get days since last login
    /// </summary>
    /// <returns>int|null</returns>
    private function getDaysSinceLastLogin(): ?int
    {
        return $this->last_login_date ? $this->last_login_date->diffInDays(now()) : null;
    }

    /// <summary>
    /// Get productivity score (0-100)
    /// </summary>
    /// <returns>int</returns>
    private function getProductivityScore(): int
    {
        $score = 0;

        // Login frequency (max 30 points)
        $daysSinceLogin = $this->getDaysSinceLastLogin();
        if ($daysSinceLogin !== null) {
            if ($daysSinceLogin <= 1) {
                $score += 30;
            } elseif ($daysSinceLogin <= 7) {
                $score += 20;
            } elseif ($daysSinceLogin <= 30) {
                $score += 10;
            }
        }

        // Alert management activity (max 25 points)
        if ($this->relationLoaded('acknowledgedAlerts') && $this->relationLoaded('closedAlerts')) {
            $alertActivity = $this->acknowledgedAlerts->count() + $this->closedAlerts->count();
            if ($alertActivity >= 50) {
                $score += 25;
            } elseif ($alertActivity >= 20) {
                $score += 15;
            } elseif ($alertActivity >= 5) {
                $score += 10;
            }
        }

        // Configuration management (max 20 points)
        if ($this->relationLoaded('createdAlertThresholds') && $this->relationLoaded('hostConfigurations')) {
            $configActivity = $this->createdAlertThresholds->count() + $this->hostConfigurations->count();
            if ($configActivity >= 20) {
                $score += 20;
            } elseif ($configActivity >= 10) {
                $score += 15;
            } elseif ($configActivity >= 3) {
                $score += 10;
            }
        }

        // Account longevity (max 15 points)
        $accountAge = $this->getAccountAgeDays();
        if ($accountAge >= 365) {
            $score += 15;
        } elseif ($accountAge >= 180) {
            $score += 10;
        } elseif ($accountAge >= 30) {
            $score += 5;
        }

        // Role bonus (max 10 points)
        if ($this->isAdministrator()) {
            $score += 10;
        } else {
            $score += 5;
        }

        return min(100, max(0, $score));
    }

    /// <summary>
    /// Get user engagement level
    /// </summary>
    /// <returns>string</returns>
    private function getUserEngagement(): string
    {
        $activityStatus = $this->getActivityStatus();
        $productivityScore = $this->getProductivityScore();

        if ($activityStatus === 'very_active' && $productivityScore >= 70) {
            return 'very_active';
        } elseif ($activityStatus === 'active' && $productivityScore >= 50) {
            return 'active';
        } elseif (in_array($activityStatus, ['active', 'moderately_active']) && $productivityScore >= 30) {
            return 'moderate';
        } elseif ($activityStatus === 'inactive') {
            return 'inactive';
        } else {
            return 'dormant';
        }
    }

    /// <summary>
    /// Get security profile assessment
    /// </summary>
    /// <returns>string</returns>
    private function getSecurityProfile(): string
    {
        $riskFactors = 0;

        // Check for security risks
        if ($this->relationLoaded('userSessions')) {
            $activeSessions = $this->userSessions->where('is_active', true)->count();
            if ($activeSessions > 5) {
                $riskFactors++;
            }
        }

        if ($this->isAdministrator() && $this->getDaysSinceLastLogin() > 30) {
            $riskFactors++;
        }

        if (!$this->is_active) {
            return 'secure'; // Disabled accounts are secure
        }

        if ($riskFactors >= 2) {
            return 'needs_attention';
        } elseif ($riskFactors == 1) {
            return 'standard';
        } else {
            return 'secure';
        }
    }

    /// <summary>
    /// Get recommendations for user account
    /// </summary>
    /// <returns>array</returns>
    private function getRecommendations(): array
    {
        $recommendations = [];
        $daysSinceLogin = $this->getDaysSinceLastLogin();
        $engagement = $this->getUserEngagement();

        // Activity recommendations
        if ($engagement === 'dormant') {
            $recommendations[] = 'Account appears dormant - consider deactivating if no longer needed';
        } elseif ($engagement === 'inactive' && $this->isAdministrator()) {
            $recommendations[] = 'Administrator account with low activity - review access requirements';
        }

        // Login recommendations
        if ($daysSinceLogin === null) {
            $recommendations[] = 'User has never logged in - consider providing onboarding assistance';
        } elseif ($daysSinceLogin > 90 && $this->is_active) {
            $recommendations[] = 'Long period without login - verify account is still needed';
        }

        // Security recommendations
        if ($this->relationLoaded('userSessions')) {
            $activeSessions = $this->userSessions->where('is_active', true)->count();
            if ($activeSessions > 3) {
                $recommendations[] = 'Multiple active sessions detected - review for security';
            }
        }

        // Role-specific recommendations
        if ($this->isAdministrator()) {
            if ($this->relationLoaded('createdAlertThresholds') && $this->createdAlertThresholds->isEmpty()) {
                $recommendations[] = 'Admin has not configured alert thresholds - consider system setup';
            }
        }

        // Productivity recommendations
        $productivityScore = $this->getProductivityScore();
        if ($productivityScore < 30 && $this->is_active) {
            $recommendations[] = 'Low system utilization - provide training or adjust role';
        }

        return empty($recommendations) ? ['User account appears well-managed'] : $recommendations;
    }

    /// <summary>
    /// Get role utilization assessment
    /// </summary>
    /// <returns>string</returns>
    private function getRoleUtilization(): string
    {
        if (!$this->is_active) {
            return 'disabled';
        }

        $productivityScore = $this->getProductivityScore();
        
        if ($this->isAdministrator()) {
            if ($productivityScore >= 60) {
                return 'fully_utilized';
            } elseif ($productivityScore >= 30) {
                return 'moderately_utilized';
            } else {
                return 'underutilized';
            }
        } else {
            if ($productivityScore >= 40) {
                return 'well_utilized';
            } elseif ($productivityScore >= 20) {
                return 'adequately_utilized';
            } else {
                return 'underutilized';
            }
        }
    }

    /// <summary>
    /// Get system contribution assessment
    /// </summary>
    /// <returns>string</returns>
    private function getSystemContribution(): string
    {
        if (!$this->relationLoaded('acknowledgedAlerts') || !$this->relationLoaded('closedAlerts') || !$this->relationLoaded('createdAlertThresholds')) {
            return 'unknown';
        }

        $totalContributions = $this->acknowledgedAlerts->count() + 
                             $this->closedAlerts->count() + 
                             $this->createdAlertThresholds->count();

        if ($this->relationLoaded('hostConfigurations')) {
            $totalContributions += $this->hostConfigurations->count();
        }

        if ($totalContributions >= 100) {
            return 'high_contributor';
        } elseif ($totalContributions >= 50) {
            return 'active_contributor';
        } elseif ($totalContributions >= 10) {
            return 'moderate_contributor';
        } elseif ($totalContributions > 0) {
            return 'minimal_contributor';
        } else {
            return 'no_contribution';
        }
    }

    /// <summary>
    /// Get overall account health
    /// </summary>
    /// <returns>string</returns>
    private function getAccountHealth(): string
    {
        if (!$this->is_active) {
            return 'disabled';
        }

        $engagement = $this->getUserEngagement();
        $security = $this->getSecurityProfile();
        $utilization = $this->getRoleUtilization();

        if ($engagement === 'very_active' && $security === 'secure' && $utilization === 'fully_utilized') {
            return 'excellent';
        } elseif (in_array($engagement, ['active', 'very_active']) && $security !== 'needs_attention') {
            return 'good';
        } elseif ($engagement !== 'dormant' && $security !== 'needs_attention') {
            return 'fair';
        } else {
            return 'needs_attention';
        }
    }

    /// <summary>
    /// Add custom headers to the response
    /// </summary>
    /// <param>Request $request</param>
    /// <param>$response</param>
    /// <returns>void</returns>
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'User');
    }

    #endregion
}