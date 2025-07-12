<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="UserSessionResource",
 *      type="object",
 *      title="UserSessionResource",
 *      description="User Session API Resource with computed fields",
 *      @OA\Property(property="session_id", type="integer", example=1),
 *      @OA\Property(property="user_id", type="integer", example=1),
 *      @OA\Property(property="session_token", type="string", example="abc123def456"),
 *      @OA\Property(property="login_date", type="string", format="date-time"),
 *      @OA\Property(property="last_activity_date", type="string", format="date-time"),
 *      @OA\Property(property="is_active", type="boolean", example=true),
 *      @OA\Property(property="ip_address", type="string", example="192.168.1.50"),
 *      @OA\Property(property="user_agent", type="string", example="Mozilla/5.0..."),
 *      @OA\Property(
 *          property="user",
 *          type="object",
 *          @OA\Property(property="id", type="integer"),
 *          @OA\Property(property="login", type="string"),
 *          @OA\Property(property="full_name", type="string"),
 *          @OA\Property(property="email", type="string"),
 *          @OA\Property(property="role", type="string"),
 *          @OA\Property(property="is_active", type="boolean")
 *      ),
 *      @OA\Property(
 *          property="computed_fields",
 *          type="object",
 *          @OA\Property(property="session_duration_minutes", type="integer"),
 *          @OA\Property(property="formatted_duration", type="string"),
 *          @OA\Property(property="time_since_last_activity", type="string"),
 *          @OA\Property(property="minutes_since_last_activity", type="integer"),
 *          @OA\Property(property="is_expired", type="boolean"),
 *          @OA\Property(property="is_localhost", type="boolean"),
 *          @OA\Property(property="browser_info", type="object"),
 *          @OA\Property(property="login_time_formatted", type="string"),
 *          @OA\Property(property="activity_status", type="string")
 *      ),
 *      @OA\Property(
 *          property="analysis",
 *          type="object",
 *          @OA\Property(property="security_level", type="string", enum={"secure", "warning", "suspicious"}),
 *          @OA\Property(property="session_health", type="string", enum={"active", "idle", "expired", "stale"}),
 *          @OA\Property(property="recommendations", type="array", @OA\Items(type="string")),
 *          @OA\Property(property="risk_factors", type="array", @OA\Items(type="string")),
 *          @OA\Property(property="geographic_info", type="object",
 *              @OA\Property(property="is_internal_ip", type="boolean"),
 *              @OA\Property(property="ip_type", type="string")
 *          )
 *      )
 * )
 */
class UserSessionResource extends JsonResource
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
    /// Transform user session resource into array with computed fields and analysis
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>array</returns>
    public function toArray(Request $request): array
    {
        return [
            // Basic session information
            'session_id' => $this->session_id,
            'user_id' => $this->user_id,
            'session_token' => $this->when(false, $this->session_token), // Never expose token in API responses
            'login_date' => $this->login_date,
            'last_activity_date' => $this->last_activity_date,
            'is_active' => $this->is_active,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,

            // Related user information
            'user' => $this->when($this->relationLoaded('user') && $this->user, function () {
                return [
                    'id' => $this->user->id,
                    'login' => $this->user->login,
                    'full_name' => $this->user->getFullNameAttribute(),
                    'email' => $this->user->email,
                    'role' => $this->user->role,
                    'is_active' => $this->user->is_active,
                    'last_login_date' => $this->user->last_login_date
                ];
            }),

            // Computed fields
            'computed_fields' => [
                'session_duration_minutes' => $this->getDurationInMinutes(),
                'formatted_duration' => $this->getFormattedDuration(),
                'time_since_last_activity' => $this->getTimeSinceLastActivity(),
                'minutes_since_last_activity' => $this->getMinutesSinceLastActivity(),
                'is_expired' => $this->isExpired(),
                'is_localhost' => $this->isLocalhost(),
                'browser_info' => $this->getBrowserInfo(),
                'login_time_formatted' => $this->getFormattedLoginTime(),
                'activity_status' => $this->getActivityStatus(),
                'session_age_category' => $this->getSessionAgeCategory()
            ],

            // Analysis and insights
            'analysis' => [
                'security_level' => $this->getSecurityLevel(),
                'session_health' => $this->getSessionHealth(),
                'recommendations' => $this->getRecommendations(),
                'risk_factors' => $this->getRiskFactors(),
                'geographic_info' => $this->getGeographicInfo(),
                'concurrent_sessions' => $this->getConcurrentSessionsCount()
            ]
        ];
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get time since last activity in human readable format
    /// </summary>
    /// <returns>string</returns>
    private function getTimeSinceLastActivity(): string
    {
        $minutes = $this->getMinutesSinceLastActivity();
        
        if ($minutes < 1) {
            return 'Just now';
        } elseif ($minutes < 60) {
            return $minutes . ' minutes ago';
        } elseif ($minutes < 1440) { // Less than 24 hours
            $hours = floor($minutes / 60);
            return $hours . ' hours ago';
        } else {
            $days = floor($minutes / 1440);
            return $days . ' days ago';
        }
    }

    /// <summary>
    /// Get minutes since last activity
    /// </summary>
    /// <returns>int</returns>
    private function getMinutesSinceLastActivity(): int
    {
        // return $this->last_activity_date->diffInMinutes(now());
        return $this->last_activity_date ? $this->last_activity_date->diffInMinutes(now()) : 0;
    }

    /// <summary>
    /// Get browser information from user agent
    /// </summary>
    /// <returns>array</returns>
    private function getBrowserInfo(): array
    {
        $userAgent = $this->user_agent ?? '';
        
        $browserInfo = [
            'browser' => 'Unknown',
            'platform' => 'Unknown',
            'is_mobile' => false,
            'is_bot' => false
        ];

        // Simple browser detection
        if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $browserInfo['browser'] = 'Chrome ' . $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $browserInfo['browser'] = 'Firefox ' . $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches)) {
            $browserInfo['browser'] = 'Safari ' . $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
            $browserInfo['browser'] = 'Edge ' . $matches[1];
        }

        // Platform detection
        if (strpos($userAgent, 'Windows') !== false) {
            $browserInfo['platform'] = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $browserInfo['platform'] = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $browserInfo['platform'] = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $browserInfo['platform'] = 'Android';
            $browserInfo['is_mobile'] = true;
        } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            $browserInfo['platform'] = 'iOS';
            $browserInfo['is_mobile'] = true;
        }

        // Bot detection
        $browserInfo['is_bot'] = preg_match('/bot|crawler|spider|scraper/i', $userAgent) === 1;

        return $browserInfo;
    }

    /// <summary>
    /// Get formatted login time
    /// </summary>
    /// <returns>string</returns>
    private function getFormattedLoginTime(): string
    {
        if (!$this->login_date) {
            return 'Unknown login time';
        }

        return $this->login_date->format('Y-m-d H:i:s') . ' (' . $this->login_date->diffForHumans() . ')';
    }

    /// <summary>
    /// Get activity status
    /// </summary>
    /// <returns>string</returns>
    private function getActivityStatus(): string
    {
        if (!$this->is_active) {
            return 'terminated';
        }

        $minutes = $this->getMinutesSinceLastActivity();
        
        if ($minutes < 5) {
            return 'very_active';
        } elseif ($minutes < 30) {
            return 'active';
        } elseif ($minutes < 120) {
            return 'idle';
        } elseif ($minutes < 480) {
            return 'inactive';
        } else {
            return 'stale';
        }
    }

    /// <summary>
    /// Get session age category
    /// </summary>
    /// <returns>string</returns>
    private function getSessionAgeCategory(): string
    {
        if (!$this->login_date) {
        return 'unknown';
    }
        
        $hours = $this->login_date->diffInHours(now());
        // $hours = $this->login_date ? $this->login_date->diffInHours(now()) : 0;

        if ($hours < 1) {
            return 'new';
        } elseif ($hours < 8) {
            return 'recent';
        } elseif ($hours < 24) {
            return 'day_old';
        } elseif ($hours < 168) { // 1 week
            return 'week_old';
        } else {
            return 'old';
        }
    }

    /// <summary>
    /// Get security level based on various factors
    /// </summary>
    /// <returns>string</returns>
    private function getSecurityLevel(): string
    {
        $riskFactors = $this->getRiskFactors();
        $riskCount = count($riskFactors);
        
        if ($riskCount >= 3) {
            return 'suspicious';
        } elseif ($riskCount >= 1) {
            return 'warning';
        } else {
            return 'secure';
        }
    }

    /// <summary>
    /// Get session health status
    /// </summary>
    /// <returns>string</returns>
    private function getSessionHealth(): string
    {
        if (!$this->is_active) {
            return 'expired';
        }

        $minutes = $this->getMinutesSinceLastActivity();
        
        if ($this->isExpired()) {
            return 'expired';
        } elseif ($minutes > 480) { // 8 hours
            return 'stale';
        } elseif ($minutes > 120) { // 2 hours
            return 'idle';
        } else {
            return 'active';
        }
    }

    /// <summary>
    /// Get recommendations based on session analysis
    /// </summary>
    /// <returns>array</returns>
    private function getRecommendations(): array
    {
        $recommendations = [];
        
        if ($this->isExpired() && $this->is_active) {
            $recommendations[] = 'Session has expired - consider terminating';
        }
        
        $minutes = $this->getMinutesSinceLastActivity();
        if ($minutes > 480 && $this->is_active) {
            $recommendations[] = 'Long inactive session - consider automatic timeout';
        }
        
        $browserInfo = $this->getBrowserInfo();
        if ($browserInfo['is_bot']) {
            $recommendations[] = 'Bot detected - verify legitimate usage';
        }
        
        if (!$this->isLocalhost() && $this->relationLoaded('user') && $this->user && $this->user->isAdministrator()) {
            $recommendations[] = 'Admin access from external IP - monitor closely';
        }
        
        $concurrentSessions = $this->getConcurrentSessionsCount();
        if ($concurrentSessions > 3) {
            $recommendations[] = 'Multiple concurrent sessions detected - verify user activity';
        }
        
        // $sessionHours = $this->login_date->diffInHours(now());
        $sessionHours = $this->login_date ? $this->login_date->diffInHours(now()) : 0;
        if ($sessionHours > 24) {
            $recommendations[] = 'Very long session duration - consider periodic re-authentication';
        }
        
        return empty($recommendations) ? ['Session appears normal'] : $recommendations;
    }

    /// <summary>
    /// Get risk factors for the session
    /// </summary>
    /// <returns>array</returns>
    private function getRiskFactors(): array
    {
        $riskFactors = [];
        
        $browserInfo = $this->getBrowserInfo();
        if ($browserInfo['is_bot']) {
            $riskFactors[] = 'Bot user agent detected';
        }
        
        if (!$this->isLocalhost() && $this->relationLoaded('user') && $this->user && $this->user->isAdministrator()) {
            $riskFactors[] = 'Administrative access from external IP';
        }
        
        $concurrentSessions = $this->getConcurrentSessionsCount();
        if ($concurrentSessions > 5) {
            $riskFactors[] = 'High number of concurrent sessions';
        }
        
        // $sessionHours = $this->login_date->diffInHours(now());
        $sessionHours = $this->login_date ? $this->login_date->diffInHours(now()) : 0;
        if ($sessionHours > 48) {
            $riskFactors[] = 'Extremely long session duration';
        }
        
        if ($this->isExpired() && $this->is_active) {
            $riskFactors[] = 'Active session past expiration time';
        }
        
        // Check for unusual activity patterns
        $minutes = $this->getMinutesSinceLastActivity();
        if ($minutes > 720) { // 12 hours
            $riskFactors[] = 'No activity for extended period';
        }
        
        return $riskFactors;
    }

    /// <summary>
    /// Get geographic information about the IP
    /// </summary>
    /// <returns>array</returns>
    private function getGeographicInfo(): array
    {
        $ip = $this->ip_address;
        
        // Jeśli IP jest null, zwróć domyślne wartości
        if (!$ip) {
            return [
                'is_internal_ip' => false,
                'is_localhost' => false,
                'ip_type' => 'unknown',
                'is_private_range' => false
            ];
        }
        
        return [
            'is_internal_ip' => $this->isInternalIP($ip),
            'is_localhost' => $this->isLocalhost(),
            'ip_type' => $this->getIPType($ip),
            'is_private_range' => $this->isPrivateRange($ip)
        ];
    }

    /// <summary>
    /// Get count of concurrent sessions for this user
    /// </summary>
    /// <returns>int</returns>
    private function getConcurrentSessionsCount(): int
    {
        if (!$this->relationLoaded('user')) {
            return 0;
        }
        
        return \App\Models\UserSession::where('user_id', $this->user_id)
                                    ->where('is_active', true)
                                    ->count();
    }

    /// <summary>
    /// Check if IP is in internal/private range
    /// </summary>
    /// <param>string $ip</param>
    /// <returns>bool</returns>
    private function isInternalIP(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /// <summary>
    /// Get IP address type
    /// </summary>
    /// <param>string $ip</param>
    /// <returns>string</returns>
    private function getIPType(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'IPv6';
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'IPv4';
        } else {
            return 'Invalid';
        }
    }

    /// <summary>
    /// Check if IP is in private address range
    /// </summary>
    /// <param>string $ip</param>
    /// <returns>bool</returns>
    private function isPrivateRange(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE
        ) === false;
    }

    /// <summary>
    /// Add custom headers to the response
    /// </summary>
    /// <param>Request $request</param>
    /// <param>$response</param>
    /// <returns>void</returns>
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'UserSession');
    }

    #endregion
}