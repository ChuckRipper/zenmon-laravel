<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="HostConfigurationResource",
 *      type="object",
 *      title="HostConfigurationResource",
 *      description="Host Configuration API Resource with computed fields",
 *      @OA\Property(property="configuration_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="data_collection_interval", type="integer", example=120, description="Interval in seconds"),
 *      @OA\Property(property="enable_cpu_monitoring", type="boolean", example=true),
 *      @OA\Property(property="enable_ram_monitoring", type="boolean", example=true),
 *      @OA\Property(property="enable_disk_monitoring", type="boolean", example=true),
 *      @OA\Property(property="enable_network_monitoring", type="boolean", example=true),
 *      @OA\Property(property="updated_by_user_id", type="integer", nullable=true, example=1),
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
 *          property="updated_by_user",
 *          type="object",
 *          nullable=true,
 *          @OA\Property(property="id", type="integer"),
 *          @OA\Property(property="login", type="string"),
 *          @OA\Property(property="full_name", type="string")
 *      ),
 *      @OA\Property(
 *          property="computed_fields",
 *          type="object",
 *          @OA\Property(property="monitoring_scope", type="string"),
 *          @OA\Property(property="collection_frequency", type="string"),
 *          @OA\Property(property="enabled_modules_count", type="integer"),
 *          @OA\Property(property="monitoring_coverage", type="number", format="float"),
 *          @OA\Property(property="configuration_age_days", type="integer"),
 *          @OA\Property(property="is_fully_monitored", type="boolean"),
 *          @OA\Property(property="estimated_data_volume", type="string"),
 *          @OA\Property(property="collection_intensity", type="string")
 *      ),
 *      @OA\Property(
 *          property="analysis",
 *          type="object",
 *          @OA\Property(property="configuration_quality", type="string", enum={"optimal", "good", "suboptimal", "poor"}),
 *          @OA\Property(property="performance_impact", type="string", enum={"minimal", "low", "moderate", "high"}),
 *          @OA\Property(property="recommendations", type="array", @OA\Items(type="string")),
 *          @OA\Property(property="optimization_suggestions", type="array", @OA\Items(type="string")),
 *          @OA\Property(property="monitoring_effectiveness", type="string"),
 *          @OA\Property(property="resource_efficiency", type="string")
 *      )
 * )
 */
class HostConfigurationResource extends JsonResource
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
    /// Transform host configuration resource into array with computed fields and analysis
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>array</returns>
    public function toArray(Request $request): array
    {
        return [
            // Basic configuration information
            'configuration_id' => $this->configuration_id,
            'host_id' => $this->host_id,
            'data_collection_interval' => $this->data_collection_interval,
            'enable_cpu_monitoring' => $this->enable_cpu_monitoring,
            'enable_ram_monitoring' => $this->enable_ram_monitoring,
            'enable_disk_monitoring' => $this->enable_disk_monitoring,
            'enable_network_monitoring' => $this->enable_network_monitoring,
            'updated_by_user_id' => $this->updated_by_user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Related host information
            'host' => $this->when($this->relationLoaded('host'), function () {
                return [
                    'host_id' => $this->host->host_id,
                    'host_name' => $this->host->host_name,
                    'ip_address' => $this->host->ip_address,
                    'operating_system' => $this->host->operating_system,
                    'agent_version' => $this->host->agent_version,
                    'is_active' => $this->host->is_active,
                    'last_contact_date' => $this->host->last_contact_date
                ];
            }),

            // User who last updated configuration
            'updated_by_user' => $this->when($this->relationLoaded('updatedByUser') && $this->updatedByUser, function () {
                return [
                    'id' => $this->updatedByUser->id,
                    'login' => $this->updatedByUser->login,
                    'full_name' => $this->updatedByUser->getFullNameAttribute(),
                    'role' => $this->updatedByUser->role
                ];
            }),

            // Monitoring modules summary
            'monitoring_modules' => [
                'cpu' => [
                    'enabled' => $this->enable_cpu_monitoring,
                    'status' => $this->enable_cpu_monitoring ? 'active' : 'disabled',
                    'priority' => 'high'
                ],
                'ram' => [
                    'enabled' => $this->enable_ram_monitoring,
                    'status' => $this->enable_ram_monitoring ? 'active' : 'disabled',
                    'priority' => 'high'
                ],
                'disk' => [
                    'enabled' => $this->enable_disk_monitoring,
                    'status' => $this->enable_disk_monitoring ? 'active' : 'disabled',
                    'priority' => 'medium'
                ],
                'network' => [
                    'enabled' => $this->enable_network_monitoring,
                    'status' => $this->enable_network_monitoring ? 'active' : 'disabled',
                    'priority' => 'medium'
                ]
            ],

            // Computed fields
            'computed_fields' => [
                'monitoring_scope' => $this->getMonitoringScope(),
                'collection_frequency' => $this->getCollectionFrequency(),
                'enabled_modules_count' => $this->getEnabledModulesCount(),
                'monitoring_coverage' => $this->getMonitoringCoverage(),
                'configuration_age_days' => $this->getConfigurationAgeDays(),
                'is_fully_monitored' => $this->isFullyMonitored(),
                'estimated_data_volume' => $this->getEstimatedDataVolume(),
                'collection_intensity' => $this->getCollectionIntensity(),
                'optimization_score' => $this->getOptimizationScore()
            ],

            // Analysis and insights
            'analysis' => [
                'configuration_quality' => $this->getConfigurationQuality(),
                'performance_impact' => $this->getPerformanceImpact(),
                'recommendations' => $this->getRecommendations(),
                'optimization_suggestions' => $this->getOptimizationSuggestions(),
                'monitoring_effectiveness' => $this->getMonitoringEffectiveness(),
                'resource_efficiency' => $this->getResourceEfficiency(),
                'compliance_status' => $this->getComplianceStatus()
            ]
        ];
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get monitoring scope description
    /// </summary>
    /// <returns>string</returns>
    private function getMonitoringScope(): string
    {
        $enabledCount = $this->getEnabledModulesCount();
        
        if ($enabledCount == 4) {
            return 'comprehensive';
        } elseif ($enabledCount == 3) {
            return 'extensive';
        } elseif ($enabledCount == 2) {
            return 'basic';
        } elseif ($enabledCount == 1) {
            return 'minimal';
        } else {
            return 'none';
        }
    }

    /// <summary>
    /// Get collection frequency description
    /// </summary>
    /// <returns>string</returns>
    private function getCollectionFrequency(): string
    {
        $interval = $this->data_collection_interval;
        
        if ($interval <= 30) {
            return 'very_frequent';
        } elseif ($interval <= 60) {
            return 'frequent';
        } elseif ($interval <= 300) { // 5 minutes
            return 'normal';
        } elseif ($interval <= 900) { // 15 minutes
            return 'infrequent';
        } else {
            return 'very_infrequent';
        }
    }

    /// <summary>
    /// Get count of enabled monitoring modules
    /// </summary>
    /// <returns>int</returns>
    private function getEnabledModulesCount(): int
    {
        $count = 0;
        if ($this->enable_cpu_monitoring) $count++;
        if ($this->enable_ram_monitoring) $count++;
        if ($this->enable_disk_monitoring) $count++;
        if ($this->enable_network_monitoring) $count++;
        return $count;
    }

    /// <summary>
    /// Get monitoring coverage percentage
    /// </summary>
    /// <returns>float</returns>
    private function getMonitoringCoverage(): float
    {
        return round(($this->getEnabledModulesCount() / 4) * 100, 2);
    }

    /// <summary>
    /// Get configuration age in days
    /// </summary>
    /// <returns>int</returns>
    private function getConfigurationAgeDays(): int
    {
        return $this->updated_at->diffInDays(now());
    }

    /// <summary>
    /// Check if host is fully monitored
    /// </summary>
    /// <returns>bool</returns>
    private function isFullyMonitored(): bool
    {
        return $this->enable_cpu_monitoring && 
               $this->enable_ram_monitoring && 
               $this->enable_disk_monitoring && 
               $this->enable_network_monitoring;
    }

    /// <summary>
    /// Get estimated daily data volume
    /// </summary>
    /// <returns>string</returns>
    private function getEstimatedDataVolume(): string
    {
        $enabledModules = $this->getEnabledModulesCount();
        $dailyCollections = (24 * 60 * 60) / $this->data_collection_interval;
        $totalDataPoints = $enabledModules * $dailyCollections;
        
        if ($totalDataPoints < 1000) {
            return 'low';
        } elseif ($totalDataPoints < 5000) {
            return 'moderate';
        } elseif ($totalDataPoints < 15000) {
            return 'high';
        } else {
            return 'very_high';
        }
    }

    /// <summary>
    /// Get collection intensity classification
    /// </summary>
    /// <returns>string</returns>
    private function getCollectionIntensity(): string
    {
        $frequency = $this->getCollectionFrequency();
        $coverage = $this->getMonitoringCoverage();
        
        if ($frequency === 'very_frequent' && $coverage >= 75) {
            return 'intensive';
        } elseif ($frequency === 'frequent' && $coverage >= 50) {
            return 'moderate';
        } elseif ($coverage >= 25) {
            return 'light';
        } else {
            return 'minimal';
        }
    }

    /// <summary>
    /// Get optimization score (0-100)
    /// </summary>
    /// <returns>int</returns>
    private function getOptimizationScore(): int
    {
        $score = 0;
        
        // Coverage score (max 40 points)
        $score += $this->getMonitoringCoverage() * 0.4;
        
        // Frequency score (max 30 points)
        $interval = $this->data_collection_interval;
        if ($interval >= 60 && $interval <= 300) { // Optimal range
            $score += 30;
        } elseif ($interval >= 30 && $interval <= 600) { // Good range
            $score += 20;
        } elseif ($interval >= 15 && $interval <= 900) { // Acceptable range
            $score += 10;
        }
        
        // Essential monitoring (max 20 points)
        if ($this->enable_cpu_monitoring) $score += 5;
        if ($this->enable_ram_monitoring) $score += 5;
        if ($this->enable_disk_monitoring) $score += 5;
        if ($this->enable_network_monitoring) $score += 5;
        
        // Configuration freshness (max 10 points)
        $ageDays = $this->getConfigurationAgeDays();
        if ($ageDays <= 30) {
            $score += 10;
        } elseif ($ageDays <= 90) {
            $score += 5;
        }
        
        return min(100, max(0, round($score)));
    }

    /// <summary>
    /// Get configuration quality assessment
    /// </summary>
    /// <returns>string</returns>
    private function getConfigurationQuality(): string
    {
        $score = $this->getOptimizationScore();
        
        if ($score >= 85) {
            return 'optimal';
        } elseif ($score >= 70) {
            return 'good';
        } elseif ($score >= 50) {
            return 'suboptimal';
        } else {
            return 'poor';
        }
    }

    /// <summary>
    /// Get performance impact assessment
    /// </summary>
    /// <returns>string</returns>
    private function getPerformanceImpact(): string
    {
        $intensity = $this->getCollectionIntensity();
        $interval = $this->data_collection_interval;
        
        if ($intensity === 'intensive' || $interval <= 15) {
            return 'high';
        } elseif ($intensity === 'moderate' || $interval <= 60) {
            return 'moderate';
        } elseif ($intensity === 'light') {
            return 'low';
        } else {
            return 'minimal';
        }
    }

    /// <summary>
    /// Get recommendations for configuration
    /// </summary>
    /// <returns>array</returns>
    private function getRecommendations(): array
    {
        $recommendations = [];
        $enabledCount = $this->getEnabledModulesCount();
        $interval = $this->data_collection_interval;
        $ageDays = $this->getConfigurationAgeDays();
        
        // Coverage recommendations
        if ($enabledCount == 0) {
            $recommendations[] = 'No monitoring enabled - activate essential monitoring modules';
        } elseif ($enabledCount < 2) {
            $recommendations[] = 'Limited monitoring scope - consider enabling additional modules';
        }
        
        // Essential modules recommendations
        if (!$this->enable_cpu_monitoring) {
            $recommendations[] = 'CPU monitoring disabled - highly recommended for system health';
        }
        if (!$this->enable_ram_monitoring) {
            $recommendations[] = 'RAM monitoring disabled - essential for performance monitoring';
        }
        
        // Frequency recommendations
        if ($interval < 30) {
            $recommendations[] = 'Very frequent collection - consider increasing interval to reduce load';
        } elseif ($interval > 600) {
            $recommendations[] = 'Infrequent collection - consider reducing interval for better visibility';
        }
        
        // Age recommendations
        if ($ageDays > 180) {
            $recommendations[] = 'Configuration is old - review and update based on current requirements';
        }
        
        // Host-specific recommendations
        if ($this->relationLoaded('host')) {
            if (!$this->host->is_active) {
                $recommendations[] = 'Host is inactive - configuration changes will not take effect';
            }
            
            $os = strtolower($this->host->operating_system ?? '');
            if (strpos($os, 'windows') !== false && !$this->enable_disk_monitoring) {
                $recommendations[] = 'Windows host without disk monitoring - recommended for disk health';
            }
        }
        
        return empty($recommendations) ? ['Configuration appears well-optimized'] : $recommendations;
    }

    /// <summary>
    /// Get optimization suggestions
    /// </summary>
    /// <returns>array</returns>
    private function getOptimizationSuggestions(): array
    {
        $suggestions = [];
        $interval = $this->data_collection_interval;
        $intensity = $this->getCollectionIntensity();
        
        // Performance optimization
        if ($intensity === 'intensive') {
            $suggestions[] = 'Reduce collection frequency to minimize system impact';
            $suggestions[] = 'Consider disabling non-critical monitoring modules during peak hours';
        }
        
        // Coverage optimization
        if ($this->getMonitoringCoverage() < 75) {
            $suggestions[] = 'Enable comprehensive monitoring for better system visibility';
        }
        
        // Frequency optimization
        if ($interval < 60 && $this->getEnabledModulesCount() >= 3) {
            $suggestions[] = 'Increase collection interval to 2-5 minutes for better performance';
        } elseif ($interval > 300 && $this->getEnabledModulesCount() <= 2) {
            $suggestions[] = 'Consider reducing interval to 2-3 minutes for better monitoring';
        }
        
        // Strategic suggestions
        if ($this->enable_cpu_monitoring && $this->enable_ram_monitoring && !$this->enable_disk_monitoring) {
            $suggestions[] = 'Add disk monitoring to complete system resource monitoring';
        }
        
        if ($this->getConfigurationAgeDays() > 90) {
            $suggestions[] = 'Review configuration against current best practices';
        }
        
        return $suggestions;
    }

    /// <summary>
    /// Get monitoring effectiveness assessment
    /// </summary>
    /// <returns>string</returns>
    private function getMonitoringEffectiveness(): string
    {
        $coverage = $this->getMonitoringCoverage();
        $frequency = $this->getCollectionFrequency();
        
        if ($coverage >= 75 && in_array($frequency, ['normal', 'frequent'])) {
            return 'high';
        } elseif ($coverage >= 50 && $frequency !== 'very_infrequent') {
            return 'moderate';
        } elseif ($coverage >= 25) {
            return 'low';
        } else {
            return 'poor';
        }
    }

    /// <summary>
    /// Get resource efficiency assessment
    /// </summary>
    /// <returns>string</returns>
    private function getResourceEfficiency(): string
    {
        $impact = $this->getPerformanceImpact();
        $effectiveness = $this->getMonitoringEffectiveness();
        
        if ($effectiveness === 'high' && in_array($impact, ['minimal', 'low'])) {
            return 'excellent';
        } elseif ($effectiveness === 'moderate' && $impact !== 'high') {
            return 'good';
        } elseif ($effectiveness !== 'poor') {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /// <summary>
    /// Get compliance status with monitoring best practices
    /// </summary>
    /// <returns>string</returns>
    private function getComplianceStatus(): string
    {
        $score = 0;
        
        // Essential monitoring compliance
        if ($this->enable_cpu_monitoring) $score += 25;
        if ($this->enable_ram_monitoring) $score += 25;
        if ($this->enable_disk_monitoring) $score += 20;
        if ($this->enable_network_monitoring) $score += 15;
        
        // Frequency compliance (60-300 seconds is optimal)
        if ($this->data_collection_interval >= 60 && $this->data_collection_interval <= 300) {
            $score += 15;
        }
        
        if ($score >= 90) {
            return 'fully_compliant';
        } elseif ($score >= 70) {
            return 'mostly_compliant';
        } elseif ($score >= 50) {
            return 'partially_compliant';
        } else {
            return 'non_compliant';
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
        $response->header('X-Resource-Type', 'HostConfiguration');
    }

    #endregion
}