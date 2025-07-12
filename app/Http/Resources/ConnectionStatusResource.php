<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="ConnectionStatusResource",
 *      type="object",
 *      title="ConnectionStatusResource",
 *      description="Connection Status API Resource with computed fields",
 *      @OA\Property(property="status_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="status", type="string", enum={"Online", "Offline", "Unknown"}, example="Online"),
 *      @OA\Property(property="last_check_date", type="string", format="date-time"),
 *      @OA\Property(property="response_time", type="integer", example=45, description="Response time in milliseconds"),
 *      @OA\Property(property="error_message", type="string", nullable=true, example=null),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(
 *          property="host",
 *          type="object",
 *          @OA\Property(property="host_id", type="integer"),
 *          @OA\Property(property="host_name", type="string"),
 *          @OA\Property(property="ip_address", type="string"),
 *          @OA\Property(property="operating_system", type="string"),
 *          @OA\Property(property="is_active", type="boolean")
 *      ),
 *      @OA\Property(
 *          property="computed_fields",
 *          type="object",
 *          @OA\Property(property="connection_quality", type="string"),
 *          @OA\Property(property="last_check_age", type="string"),
 *          @OA\Property(property="minutes_since_check", type="integer"),
 *          @OA\Property(property="is_check_fresh", type="boolean"),
 *          @OA\Property(property="is_responsive", type="boolean"),
 *          @OA\Property(property="status_category", type="string"),
 *          @OA\Property(property="formatted_response_time", type="string"),
 *          @OA\Property(property="uptime_status", type="string")
 *      ),
 *      @OA\Property(
 *          property="analysis",
 *          type="object",
 *          @OA\Property(property="health_assessment", type="string", enum={"excellent", "good", "poor", "critical", "unknown"}),
 *          @OA\Property(property="reliability_score", type="integer", description="Score from 0-100"),
 *          @OA\Property(property="recommendations", type="array", @OA\Items(type="string")),
 *          @OA\Property(property="status_trends", type="object",
 *              @OA\Property(property="stability", type="string"),
 *              @OA\Property(property="performance_trend", type="string")
 *          ),
 *          @OA\Property(property="monitoring_quality", type="string")
 *      )
 * )
 */
class ConnectionStatusResource extends JsonResource
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
    /// Transform connection status resource into array with computed fields and analysis
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>array</returns>
    public function toArray(Request $request): array
    {
        return [
            // Basic connection status information
            'status_id' => $this->status_id,
            'host_id' => $this->host_id,
            'status' => $this->status,
            'last_check_date' => $this->last_check_date,
            'response_time' => $this->response_time,
            'error_message' => $this->error_message,
            // 'created_at' => $this->created_at,
            // 'updated_at' => $this->updated_at,

            // Related host information
            'host' => $this->when($this->relationLoaded('host') && $this->host, function () {
                return [
                    'host_id' => $this->host->host_id,
                    'host_name' => $this->host->host_name,
                    'ip_address' => $this->host->ip_address,
                    'operating_system' => $this->host->operating_system,
                    'is_active' => $this->host->is_active,
                    'agent_version' => $this->host->agent_version,
                    'last_contact_date' => $this->host->last_contact_date
                ];
            }),

            // Recent status history (if loaded)
            'status_history' => $this->when($this->relationLoaded('recentStatuses'), function () {
                return $this->recentStatuses->map(function ($status) {
                    return [
                        'status' => $status->status,
                        'response_time' => $status->response_time,
                        'last_check_date' => $status->last_check_date,
                        'error_message' => $status->error_message
                    ];
                });
            }),

            // Computed fields
            'computed_fields' => [
                'connection_quality' => $this->getConnectionQuality(),
                'last_check_age' => $this->getLastCheckAge(),
                'minutes_since_check' => $this->getMinutesSinceCheck(),
                'is_check_fresh' => $this->isCheckFresh(),
                'is_responsive' => $this->isResponsive(),
                'status_category' => $this->getStatusCategory(),
                'formatted_response_time' => $this->getFormattedResponseTime(),
                'uptime_status' => $this->getUptimeStatus(),
                'connectivity_score' => $this->getConnectivityScore()
            ],

            // Analysis and insights
            'analysis' => [
                'health_assessment' => $this->getHealthAssessment(),
                'reliability_score' => $this->getReliabilityScore(),
                'recommendations' => $this->getRecommendations(),
                'status_trends' => $this->getStatusTrends(),
                'monitoring_quality' => $this->getMonitoringQuality(),
                'performance_analysis' => $this->getPerformanceAnalysis()
            ]
        ];
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get connection quality based on response time and status
    /// </summary>
    /// <returns>string</returns>
    private function getConnectionQuality(): string
    {
        if ($this->status !== 'Online') {
            return 'unavailable';
        }

        if ($this->response_time === null) {
            return 'unknown';
        }

        if ($this->response_time <= 50) {
            return 'excellent';
        } elseif ($this->response_time <= 150) {
            return 'good';
        } elseif ($this->response_time <= 500) {
            return 'fair';
        } elseif ($this->response_time <= 1500) {
            return 'poor';
        } else {
            return 'very_poor';
        }
    }

    /// <summary>
    /// Get human readable time since last check
    /// </summary>
    /// <returns>string</returns>
    private function getLastCheckAge(): string
    {
        $minutes = $this->getMinutesSinceCheck();
        
        if ($minutes < 1) {
            return 'just now';
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
    /// Get minutes since last check
    /// </summary>
    /// <returns>int</returns>
    private function getMinutesSinceCheck(): int
    {
        if (!$this->last_check_date) {
        return 0;
        }
        
        return $this->last_check_date->diffInMinutes(now());
    }

    /// <summary>
    /// Check if the check is fresh (within last 10 minutes)
    /// </summary>
    /// <returns>bool</returns>
    private function isCheckFresh(): bool
    {
        return $this->getMinutesSinceCheck() <= 10;
    }

    /// <summary>
    /// Check if host is responsive (online with reasonable response time)
    /// </summary>
    /// <returns>bool</returns>
    private function isResponsive(): bool
    {
        return $this->status === 'Online' && 
               $this->response_time !== null && 
               $this->response_time <= 3000; // 3 seconds
    }

    /// <summary>
    /// Get status category
    /// </summary>
    /// <returns>string</returns>
    private function getStatusCategory(): string
    {
        switch ($this->status) {
            case 'Online':
                if ($this->response_time <= 200) {
                    return 'healthy';
                } elseif ($this->response_time <= 1000) {
                    return 'slow';
                } else {
                    return 'very_slow';
                }
            case 'Offline':
                return 'down';
            case 'Unknown':
                return 'unknown';
            default:
                return 'undefined';
        }
    }

    /// <summary>
    /// Get formatted response time
    /// </summary>
    /// <returns>string</returns>
    private function getFormattedResponseTime(): string
    {
        if ($this->response_time === null) {
            return 'N/A';
        }

        if ($this->response_time < 1000) {
            return $this->response_time . ' ms';
        } else {
            return round($this->response_time / 1000, 2) . ' s';
        }
    }

    /// <summary>
    /// Get uptime status description
    /// </summary>
    /// <returns>string</returns>
    private function getUptimeStatus(): string
    {
        if ($this->status === 'Online') {
            $quality = $this->getConnectionQuality();
            if (in_array($quality, ['excellent', 'good'])) {
                return 'operational';
            } else {
                return 'degraded';
            }
        } elseif ($this->status === 'Offline') {
            return 'outage';
        } else {
            return 'monitoring_issue';
        }
    }

    /// <summary>
    /// Get connectivity score (0-100)
    /// </summary>
    /// <returns>int</returns>
    private function getConnectivityScore(): int
    {
        $score = 0;

        // Base score for status
        if ($this->status === 'Online') {
            $score += 60;
        } elseif ($this->status === 'Unknown') {
            $score += 20;
        }
        // Offline = 0

        // Response time bonus (max 30 points)
        if ($this->response_time !== null && $this->status === 'Online') {
            if ($this->response_time <= 50) {
                $score += 30;
            } elseif ($this->response_time <= 150) {
                $score += 25;
            } elseif ($this->response_time <= 500) {
                $score += 15;
            } elseif ($this->response_time <= 1500) {
                $score += 5;
            }
        }

        // Freshness bonus (max 10 points)
        $minutes = $this->getMinutesSinceCheck();
        if ($minutes <= 5) {
            $score += 10;
        } elseif ($minutes <= 15) {
            $score += 5;
        }

        return min(100, max(0, $score));
    }

    /// <summary>
    /// Get health assessment
    /// </summary>
    /// <returns>string</returns>
    private function getHealthAssessment(): string
    {
        $score = $this->getConnectivityScore();
        $minutes = $this->getMinutesSinceCheck();
        
        if ($minutes > 60) {
            return 'unknown'; // Data too old
        }

        if ($score >= 90) {
            return 'excellent';
        } elseif ($score >= 70) {
            return 'good';
        } elseif ($score >= 40) {
            return 'poor';
        } else {
            return 'critical';
        }
    }

    /// <summary>
    /// Get reliability score based on historical data
    /// </summary>
    /// <returns>int</returns>
    private function getReliabilityScore(): int
    {
        // Base score from current status
        $baseScore = $this->getConnectivityScore();
        
        // If no historical data loaded, return base score
        if (!$this->relationLoaded('recentStatuses')) {
            return $baseScore;
        }

        // Calculate based on recent status history
        $recentStatuses = $this->recentStatuses ?? collect();
        
        if ($recentStatuses->isEmpty()) {
            return $baseScore;
        }

        $onlineCount = $recentStatuses->where('status', 'Online')->count();
        $totalCount = $recentStatuses->count();
        
        $uptimePercentage = ($onlineCount / $totalCount) * 100;
        
        // Weight current status more heavily
        $reliabilityScore = ($baseScore * 0.4) + ($uptimePercentage * 0.6);
        
        return round($reliabilityScore);
    }

    /// <summary>
    /// Get recommendations based on status analysis
    /// </summary>
    /// <returns>array</returns>
    private function getRecommendations(): array
    {
        $recommendations = [];
        $minutes = $this->getMinutesSinceCheck();
        
        // Data freshness recommendations
        if ($minutes > 60) {
            $recommendations[] = 'Connection monitoring data is stale - check monitoring agent';
        } elseif ($minutes > 15) {
            $recommendations[] = 'Consider increasing monitoring frequency';
        }

        // Status-specific recommendations
        switch ($this->status) {
            case 'Offline':
                $recommendations[] = 'Host is offline - investigate connectivity issues';
                $recommendations[] = 'Check network configuration and agent status';
                break;
                
            case 'Unknown':
                $recommendations[] = 'Connection status unknown - verify monitoring configuration';
                break;
                
            case 'Online':
                if ($this->response_time > 1000) {
                    $recommendations[] = 'High response time detected - investigate network performance';
                } elseif ($this->response_time > 500) {
                    $recommendations[] = 'Elevated response time - monitor for degradation';
                }
                break;
        }

        // Error message recommendations
        if ($this->error_message) {
            $recommendations[] = 'Connection errors detected - review error details';
        }

        // Host-specific recommendations
        if ($this->relationLoaded('host') && $this->host && !$this->host->is_active) {
            $recommendations[] = 'Host is marked as inactive - consider reactivating if needed';
        }

        return empty($recommendations) ? ['Connection monitoring appears healthy'] : $recommendations;
    }

    /// <summary>
    /// Get status trends analysis
    /// </summary>
    /// <returns>array</returns>
    private function getStatusTrends(): array
    {
        $trends = [
            'stability' => 'stable',
            'performance_trend' => 'stable'
        ];

        if (!$this->relationLoaded('recentStatuses')) {
            return $trends;
        }

        $recentStatuses = $this->recentStatuses ?? collect();
        
        if ($recentStatuses->count() < 3) {
            return $trends;
        }

        // Stability analysis
        $statusChanges = 0;
        $previousStatus = null;
        
        foreach ($recentStatuses as $status) {
            if ($previousStatus && $previousStatus !== $status->status) {
                $statusChanges++;
            }
            $previousStatus = $status->status;
        }

        if ($statusChanges >= 3) {
            $trends['stability'] = 'unstable';
        } elseif ($statusChanges >= 1) {
            $trends['stability'] = 'fluctuating';
        }

        // Performance trend analysis
        $onlineStatuses = $recentStatuses->where('status', 'Online')->where('response_time', '!=', null);
        
        if ($onlineStatuses->count() >= 3) {
            $avgResponseTime = $onlineStatuses->avg('response_time');
            $recentAvg = $onlineStatuses->take(3)->avg('response_time');
            
            if ($recentAvg > $avgResponseTime * 1.2) {
                $trends['performance_trend'] = 'degrading';
            } elseif ($recentAvg < $avgResponseTime * 0.8) {
                $trends['performance_trend'] = 'improving';
            }
        }

        return $trends;
    }

    /// <summary>
    /// Get monitoring quality assessment
    /// </summary>
    /// <returns>string</returns>
    private function getMonitoringQuality(): string
    {
        $minutes = $this->getMinutesSinceCheck();
        
        if ($minutes <= 5) {
            return 'excellent';
        } elseif ($minutes <= 15) {
            return 'good';
        } elseif ($minutes <= 60) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /// <summary>
    /// Get performance analysis
    /// </summary>
    /// <returns>array</returns>
    private function getPerformanceAnalysis(): array
    {
        $analysis = [
            'current_performance' => 'unknown',
            'baseline_comparison' => 'no_baseline',
            'performance_category' => 'unknown'
        ];

        if ($this->status !== 'Online' || $this->response_time === null) {
            return $analysis;
        }

        // Current performance
        if ($this->response_time <= 100) {
            $analysis['current_performance'] = 'excellent';
        } elseif ($this->response_time <= 300) {
            $analysis['current_performance'] = 'good';
        } elseif ($this->response_time <= 1000) {
            $analysis['current_performance'] = 'acceptable';
        } else {
            $analysis['current_performance'] = 'poor';
        }

        // Performance category
        if ($this->response_time <= 50) {
            $analysis['performance_category'] = 'high_performance';
        } elseif ($this->response_time <= 200) {
            $analysis['performance_category'] = 'standard';
        } elseif ($this->response_time <= 1000) {
            $analysis['performance_category'] = 'slow';
        } else {
            $analysis['performance_category'] = 'very_slow';
        }

        // Baseline comparison if historical data available
        if ($this->relationLoaded('recentStatuses')) {
            $recentOnline = $this->recentStatuses->where('status', 'Online')->where('response_time', '!=', null);
            
            if ($recentOnline->count() >= 5) {
                $avgResponseTime = $recentOnline->avg('response_time');
                
                if ($this->response_time <= $avgResponseTime * 0.8) {
                    $analysis['baseline_comparison'] = 'better_than_average';
                } elseif ($this->response_time >= $avgResponseTime * 1.5) {
                    $analysis['baseline_comparison'] = 'worse_than_average';
                } else {
                    $analysis['baseline_comparison'] = 'within_normal_range';
                }
            }
        }

        return $analysis;
    }

    /// <summary>
    /// Add custom headers to the response
    /// </summary>
    /// <param>Request $request</param>
    /// <param>$response</param>
    /// <returns>void</returns>
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'ConnectionStatus');
    }

    #endregion
}