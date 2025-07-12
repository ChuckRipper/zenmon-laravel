<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="HostResponse",
 *      type="object",
 *      title="HostResponse", 
 *      description="Host API Resource with comprehensive monitoring information",
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="host_name", type="string", example="web-server-01"),
 *      @OA\Property(property="ip_address", type="string", example="192.168.1.100"),
 *      @OA\Property(property="description", type="string", nullable=true, example="Production web server"),
 *      @OA\Property(property="operating_system", type="string", nullable=true, example="Ubuntu 22.04 LTS"),
 *      @OA\Property(property="agent_version", type="string", nullable=true, example="2.0.1"),
 *      @OA\Property(property="is_active", type="boolean", example=true),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(property="configuration", type="object", nullable=true,
 *          @OA\Property(property="monitoring_interval", type="integer", example=60),
 *          @OA\Property(property="alert_thresholds_enabled", type="boolean", example=true),
 *          @OA\Property(property="updated_by", type="string", example="admin")
 *      ),
 *      @OA\Property(property="connection_status", type="object", nullable=true,
 *          @OA\Property(property="status", type="string", example="online"),
 *          @OA\Property(property="response_time", type="integer", example=150),
 *          @OA\Property(property="last_check_date", type="string", format="date-time"),
 *          @OA\Property(property="error_message", type="string", nullable=true)
 *      ),
 *      @OA\Property(property="health_summary", type="object",
 *          @OA\Property(property="status", type="string", enum={"healthy", "warning", "critical", "unknown"}),
 *          @OA\Property(property="uptime_percentage", type="number", format="float", example=99.5),
 *          @OA\Property(property="last_metric_age_hours", type="number", format="float", nullable=true)
 *      ),
 *      @OA\Property(property="counts", type="object",
 *          @OA\Property(property="active_alerts_count", type="integer", example=2),
 *          @OA\Property(property="monitored_directories_count", type="integer", example=5),
 *          @OA\Property(property="total_metrics_24h", type="integer", example=1440)
 *      )
 * )
 */
class HostResource extends JsonResource
{
    #region Properties
    
    /// <summary>
    /// Additional data to include when transforming the resource
    /// </summary>
    public static $wrap = null;
    
    #endregion

    #region Constructors

    /// <summary>
    /// Create a new resource instance
    /// </summary>
    /// <param name="mixed">$resource</param>
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    #endregion

    #region Methods
    
    /// <summary>
    /// Transform the resource into an array.
    /// </summary>
    /// <param name="Request">$request</param>
    /// <returns>array<string, mixed></returns>
    public function toArray(Request $request): array
    {
        return [
            // Basic host information
            'host_id' => $this->host_id,
            'host_name' => $this->host_name,
            'ip_address' => $this->ip_address,
            'description' => $this->description,
            'operating_system' => $this->operating_system,
            'agent_version' => $this->agent_version,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Configuration when loaded
            'configuration' => $this->when($this->relationLoaded('configuration') && $this->configuration, function () {
                return [
                    'monitoring_interval' => $this->configuration->data_collection_interval ?? 120,
                    'alert_thresholds_enabled' => $this->configuration->enable_cpu_monitoring ?? true,
                    'cpu_threshold_warning' => 70.0,
                    'cpu_threshold_critical' => 90.0,
                    'memory_threshold_warning' => 80.0,
                    'memory_threshold_critical' => 95.0,
                    'disk_threshold_warning' => 85.0,
                    'disk_threshold_critical' => 95.0,
                    'updated_at' => $this->configuration->updated_at,
                    'updated_by' => $this->configuration->updatedByUser?->first_name ?? 'System'
                ];
            }),

            // Latest connection status when loaded
            'connection_status' => $this->when($this->relationLoaded('connectionStatuses'), function () {
                $latestStatus = $this->connectionStatuses()->latest('last_check_date')->first();
                return $latestStatus ? [
                    'status' => $latestStatus->status,
                    'response_time' => $latestStatus->response_time,
                    'last_check_date' => $latestStatus->last_check_date->toISOString(),
                    'error_message' => $latestStatus->error_message
                ] : null;
            }),

            // Health summary
            'health_summary' => [
                'status' => $this->getHealthStatus(),
                'uptime_percentage' => $this->getUptimePercentage(),
                'last_metric_age_hours' => $this->getLastMetricAgeHours(),
                'connectivity' => $this->getConnectivityStatus()
            ],

            // Counts and statistics
            'counts' => [
                'active_alerts_count' => $this->when($this->relationLoaded('alerts'), function () {
                    return $this->alerts->where('status', 'Active')->count();
                }, 0),
                'monitored_directories_count' => $this->when($this->relationLoaded('monitoredDirectories'), function () {
                    return $this->monitoredDirectories->where('is_active', true)->count();
                }, 0),
                'total_metrics_24h' => $this->when($this->relationLoaded('metrics'), function () {
                    return $this->metrics->where('timestamp', '>=', now()->subHours(24))->count();
                }, 0)
            ]
        ];
    }

    /// <summary>
    /// Get overall health status based on alerts and connection
    /// </summary>
    /// <returns>string</returns>
    private function getHealthStatus(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->relationLoaded('alerts')) {
            $activeAlerts = $this->alerts->where('status', 'Active');
            if ($activeAlerts->where('alert_level', 'Critical')->isNotEmpty()) {
                return 'critical';
            }
            if ($activeAlerts->where('alert_level', 'Warning')->isNotEmpty()) {
                return 'warning';
            }
        }

        // Check connectivity
        if ($this->relationLoaded('connectionStatuses')) {
            $latestStatus = $this->connectionStatuses()->latest('last_check_date')->first();
            if (!$latestStatus || $latestStatus->status !== 'online') {
                return 'warning';
            }
        }

        return 'healthy';
    }

    /// <summary>
    /// Calculate uptime percentage for last 7 days
    /// </summary>
    /// <returns>float</returns>
    private function getUptimePercentage(): float
    {
        if (!$this->relationLoaded('connectionStatuses')) {
            return 0.0;
        }

        $recentStatuses = $this->connectionStatuses
            ->where('last_check_date', '>=', now()->subDays(7));

        if ($recentStatuses->isEmpty()) {
            return 0.0;
        }

        $onlineCount = $recentStatuses->where('status', 'Online')->count();
        return round(($onlineCount / $recentStatuses->count()) * 100, 2);
    }

    /// <summary>
    /// Get age of last metric in hours
    /// </summary>
    /// <returns>float|null</returns>
    private function getLastMetricAgeHours(): ?float
    {
        if (!$this->relationLoaded('metrics') || $this->metrics->isEmpty()) {
            return null;
        }

        $lastMetric = $this->metrics->sortByDesc('timestamp')->first();
        return round($lastMetric->timestamp->diffInHours(now()), 2);
    }

    /// <summary>
    /// Get connectivity status summary
    /// </summary>
    /// <returns>string</returns>
    private function getConnectivityStatus(): string
    {
        if (!$this->relationLoaded('connectionStatuses')) {
            return 'unknown';
        }

        $latestStatus = $this->connectionStatuses()->latest('last_check_date')->first();
        
        if (!$latestStatus) {
            return 'unknown';
        }

        if ($latestStatus->last_check_date < now()->subMinutes(10)) {
            return 'stale';
        }

        return $latestStatus->status === 'Online' ? 'online' : 'offline';
    }

    #endregion
}