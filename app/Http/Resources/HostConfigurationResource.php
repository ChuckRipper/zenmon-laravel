<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="HostConfigurationResource",
 *      type="object",
 *      title="HostConfigurationResource",
 *      description="Host Configuration API Resource with monitoring settings",
 *      @OA\Property(property="configuration_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="data_collection_interval", type="integer", example=120),
 *      @OA\Property(property="enable_cpu_monitoring", type="boolean", example=true),
 *      @OA\Property(property="enable_ram_monitoring", type="boolean", example=true),
 *      @OA\Property(property="enable_disk_monitoring", type="boolean", example=true),
 *      @OA\Property(property="enable_network_monitoring", type="boolean", example=true),
 *      @OA\Property(property="updated_by_user_id", type="integer", nullable=true),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(
 *          property="host",
 *          type="object",
 *          @OA\Property(property="host_id", type="integer"),
 *          @OA\Property(property="host_name", type="string"),
 *          @OA\Property(property="ip_address", type="string"),
 *          @OA\Property(property="operating_system", type="string"),
 *          @OA\Property(property="agent_version", type="string"),
 *          @OA\Property(property="is_active", type="boolean"),
 *          @OA\Property(property="last_contact_date", type="string", format="date-time", nullable=true)
 *      ),
 *      @OA\Property(
 *          property="updated_by_user",
 *          type="object",
 *          nullable=true,
 *          @OA\Property(property="id", type="integer"),
 *          @OA\Property(property="login", type="string"),
 *          @OA\Property(property="full_name", type="string"),
 *          @OA\Property(property="role", type="string")
 *      ),
 *      @OA\Property(
 *          property="monitoring_modules",
 *          type="object",
 *          @OA\Property(property="cpu", type="object"),
 *          @OA\Property(property="ram", type="object"),
 *          @OA\Property(property="disk", type="object"),
 *          @OA\Property(property="network", type="object")
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

            // Related host information - FIXED NULL SAFETY
            'host' => $this->when(
                $this->relationLoaded('host') && $this->host !== null,
                function () {
                    return [
                        'host_id' => $this->host->host_id,
                        'host_name' => $this->host->host_name,
                        'ip_address' => $this->host->ip_address,
                        'operating_system' => $this->host->operating_system,
                        'agent_version' => $this->host->agent_version ?? 'Unknown',
                        'is_active' => $this->host->is_active,
                        'last_contact_date' => $this->host->last_contact_date
                    ];
                }
            ),

            // User who last updated configuration - FIXED NULL SAFETY
            'updated_by_user' => $this->when(
                $this->relationLoaded('updatedByUser') && $this->updatedByUser !== null,
                function () {
                    return [
                        'id' => $this->updatedByUser->id,
                        'login' => $this->updatedByUser->login,
                        'full_name' => $this->updatedByUser->getFullNameAttribute(),
                        'role' => $this->updatedByUser->role
                    ];
                }
            ),

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
            'computed_fields' => $this->getComputedFields(),

            // Analysis
            'analysis' => $this->getAnalysis(),
        ];
    }

    /// <summary>
    /// Calculate computed fields for configuration
    /// </summary>
    /// <returns>array</returns>
    private function getComputedFields(): array
    {
        $enabledModules = 0;
        if ($this->enable_cpu_monitoring) $enabledModules++;
        if ($this->enable_ram_monitoring) $enabledModules++;
        if ($this->enable_disk_monitoring) $enabledModules++;
        if ($this->enable_network_monitoring) $enabledModules++;

        $monitoringCoverage = ($enabledModules / 4) * 100;

        // Collection frequency description
        $collectionFrequency = match(true) {
            $this->data_collection_interval <= 60 => 'High frequency (≤1 min)',
            $this->data_collection_interval <= 300 => 'Medium frequency (1-5 min)',
            $this->data_collection_interval <= 900 => 'Low frequency (5-15 min)',
            default => 'Very low frequency (>15 min)'
        };

        // Collection intensity
        $intensity = match(true) {
            $this->data_collection_interval <= 60 && $enabledModules >= 3 => 'intensive',
            $this->data_collection_interval <= 300 && $enabledModules >= 2 => 'moderate',
            default => 'light'
        };

        return [
            'monitoring_scope' => $enabledModules >= 3 ? 'comprehensive' : ($enabledModules >= 2 ? 'standard' : 'basic'),
            'collection_frequency' => $collectionFrequency,
            'enabled_modules_count' => $enabledModules,
            'monitoring_coverage' => round($monitoringCoverage, 1),
            'configuration_age_days' => $this->updated_at ? $this->updated_at->diffInDays(now()) : 0,
            'is_fully_monitored' => $enabledModules === 4,
            'estimated_data_volume' => $this->getEstimatedDataVolume(),
            'collection_intensity' => $intensity
        ];
    }

    /// <summary>
    /// Generate analysis for configuration
    /// </summary>
    /// <returns>array</returns>
    private function getAnalysis(): array
    {
        $enabledModules = array_sum([
            $this->enable_cpu_monitoring,
            $this->enable_ram_monitoring,
            $this->enable_disk_monitoring,
            $this->enable_network_monitoring
        ]);

        $recommendations = [];
        $optimizationSuggestions = [];

        // Configuration quality assessment
        $configQuality = match(true) {
            $enabledModules === 4 && $this->data_collection_interval <= 300 => 'optimal',
            $enabledModules >= 3 && $this->data_collection_interval <= 600 => 'good',
            $enabledModules >= 2 => 'suboptimal',
            default => 'poor'
        };

        // Performance impact assessment
        $performanceImpact = match(true) {
            $this->data_collection_interval <= 60 && $enabledModules === 4 => 'high',
            $this->data_collection_interval <= 120 && $enabledModules >= 3 => 'moderate',
            $this->data_collection_interval <= 300 => 'low',
            default => 'minimal'
        };

        // Generate recommendations
        if (!$this->enable_cpu_monitoring) {
            $recommendations[] = 'Enable CPU monitoring for comprehensive system oversight';
        }
        if (!$this->enable_ram_monitoring) {
            $recommendations[] = 'Enable RAM monitoring to track memory usage';
        }
        if ($this->data_collection_interval > 300) {
            $recommendations[] = 'Consider reducing collection interval for better monitoring granularity';
        }
        if ($enabledModules < 3) {
            $recommendations[] = 'Enable additional monitoring modules for complete visibility';
        }

        // Optimization suggestions
        if ($this->data_collection_interval < 60 && $enabledModules === 4) {
            $optimizationSuggestions[] = 'Consider increasing interval to 60-120s to reduce system load';
        }
        if ($performanceImpact === 'high') {
            $optimizationSuggestions[] = 'Current configuration may impact system performance';
        }

        return [
            'configuration_quality' => $configQuality,
            'performance_impact' => $performanceImpact,
            'recommendations' => $recommendations,
            'optimization_suggestions' => $optimizationSuggestions,
            // 'monitoring_effectiveness' => $this->getMonitoringEffectiveness($enabledModules, $this->data_collection_interval),
            'monitoring_effectiveness' => $this->getMonitoringEffectiveness($enabledModules, $this->data_collection_interval ?? 0),
            // 'resource_efficiency' => $this->getResourceEfficiency($enabledModules, $this->data_collection_interval)
            'resource_efficiency' => $this->getResourceEfficiency($enabledModules, $this->data_collection_interval ?? 0)
        ];
    }

    /// <summary>
    /// Estimate data volume based on configuration
    /// </summary>
    /// <returns>string</returns>
    private function getEstimatedDataVolume(): string
    {
        $enabledModules = array_sum([
            $this->enable_cpu_monitoring,
            $this->enable_ram_monitoring,
            $this->enable_disk_monitoring,
            $this->enable_network_monitoring
        ]);

        // Rough estimation: each module generates ~1KB per minute
        // $dailyKB = $enabledModules * (24 * 60) / ($this->data_collection_interval / 60);
        $dailyKB = $this->data_collection_interval > 0 
            ? $enabledModules * (24 * 60) / ($this->data_collection_interval / 60)
            : 0;
        
        return match(true) {
            $dailyKB < 1024 => number_format($dailyKB, 1) . ' KB/day',
            $dailyKB < (1024 * 1024) => number_format($dailyKB / 1024, 1) . ' MB/day',
            default => number_format($dailyKB / (1024 * 1024), 1) . ' GB/day'
        };
    }

    /// <summary>
    /// Assess monitoring effectiveness
    /// </summary>
    /// <param>int $enabledModules</param>
    /// <param>int $interval</param>
    /// <returns>string</returns>
    private function getMonitoringEffectiveness(int $enabledModules, int $interval): string
    {
        if ($interval === null || $interval <= 0) {
            return 'not_configured';
    }
        
        return match(true) {
            $enabledModules >= 3 && $interval <= 180 => 'excellent',
            $enabledModules >= 2 && $interval <= 300 => 'good',
            $enabledModules >= 1 && $interval <= 600 => 'fair',
            default => 'limited'
        };
    }

    /// <summary>
    /// Assess resource efficiency
    /// </summary>
    /// <param>int $enabledModules</param>
    /// <param>int $interval</param>
    /// <returns>string</returns>
    private function getResourceEfficiency(int $enabledModules, int $interval): string
    {
        $score = ($enabledModules * 25) + (max(0, 600 - $interval) / 10);
        
        return match(true) {
            $score >= 80 => 'excellent',
            $score >= 60 => 'good',
            $score >= 40 => 'fair',
            default => 'poor'
        };
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