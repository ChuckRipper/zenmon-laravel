<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="MonitoredDirectoryResource",
 *      type="object",
 *      title="MonitoredDirectoryResource",
 *      description="Monitored Directory API Resource with computed fields",
 *      @OA\Property(property="directory_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="directory_path", type="string", example="/var/log"),
 *      @OA\Property(property="is_active", type="boolean", example=true),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(
 *          property="host",
 *          type="object",
 *          @OA\Property(property="host_id", type="integer"),
 *          @OA\Property(property="host_name", type="string"),
 *          @OA\Property(property="ip_address", type="string"),
 *          @OA\Property(property="operating_system", type="string")
 *      ),
 *      @OA\Property(
 *          property="latest_metric",
 *          type="object",
 *          nullable=true,
 *          @OA\Property(property="used_space", type="integer"),
 *          @OA\Property(property="total_space", type="integer"),
 *          @OA\Property(property="available_space", type="integer"),
 *          @OA\Property(property="file_count", type="integer"),
 *          @OA\Property(property="timestamp", type="string", format="date-time")
 *      ),
 *      @OA\Property(
 *          property="computed_fields",
 *          type="object",
 *          @OA\Property(property="directory_name", type="string"),
 *          @OA\Property(property="usage_percentage", type="number", format="float", nullable=true),
 *          @OA\Property(property="free_percentage", type="number", format="float", nullable=true),
 *          @OA\Property(property="is_linux_system_dir", type="boolean"),
 *          @OA\Property(property="is_windows_system_dir", type="boolean"),
 *          @OA\Property(property="formatted_used_space", type="string", nullable=true),
 *          @OA\Property(property="formatted_total_space", type="string", nullable=true),
 *          @OA\Property(property="formatted_available_space", type="string", nullable=true),
 *          @OA\Property(property="status", type="string"),
 *          @OA\Property(property="days_since_created", type="integer"),
 *          @OA\Property(property="monitoring_duration", type="string")
 *      ),
 *      @OA\Property(
 *          property="analysis",
 *          type="object",
 *          @OA\Property(property="health_status", type="string", enum={"unknown", "healthy", "warning", "critical"}),
 *          @OA\Property(property="risk_level", type="string", enum={"low", "medium", "high"}),
 *          @OA\Property(property="recommendations", type="array", @OA\Items(type="string")),
 *          @OA\Property(property="last_metric_age_hours", type="integer", nullable=true),
 *          @OA\Property(property="trend", type="string", enum={"stable", "growing", "shrinking", "unknown"})
 *      )
 * )
 */
class MonitoredDirectoryResource extends JsonResource
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
    /// Transform monitored directory resource into array with computed fields and analysis
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>array</returns>
    public function toArray(Request $request): array
    {
        return [
            // Basic directory information
            'directory_id' => $this->directory_id,
            'host_id' => $this->host_id,
            'directory_path' => $this->directory_path,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Related host information
            'host' => $this->when($this->relationLoaded('host') && $this->host, function () {
                return [
                    'host_id' => $this->host->host_id,
                    'host_name' => $this->host->host_name,
                    'ip_address' => $this->host->ip_address,
                    'operating_system' => $this->host->operating_system,
                    'is_active' => $this->host->is_active
                ];
            }),

            // Latest metric information
            'latest_metric' => $this->when($this->relationLoaded('latestMetric') && $this->latestMetric, function () {
                return [
                    'directory_metric_id' => $this->latestMetric->directory_metric_id,
                    'used_space' => $this->latestMetric->used_space,
                    'total_space' => $this->latestMetric->total_space,
                    'available_space' => $this->latestMetric->available_space,
                    'file_count' => $this->latestMetric->file_count,
                    'timestamp' => $this->latestMetric->timestamp,
                    // 'hours_ago' => $this->latestMetric->timestamp->diffInHours(now())
                    'hours_ago' => $this->latestMetric ? $this->latestMetric->timestamp->diffInHours(now()) : null,
                ];
            }),

            // Recent metrics (if loaded)
            'recent_metrics' => $this->when($this->relationLoaded('directoryMetrics') && $this->directoryMetrics->isNotEmpty(), function () {
                return $this->directoryMetrics->map(function ($metric) {
                    return [
                        'directory_metric_id' => $metric->directory_metric_id,
                        'used_space' => $metric->used_space,
                        'total_space' => $metric->total_space,
                        'available_space' => $metric->available_space,
                        'file_count' => $metric->file_count,
                        'timestamp' => $metric->timestamp,
                        'usage_percentage' => $metric->total_space > 0 ? round(($metric->used_space / $metric->total_space) * 100, 2) : null
                    ];
                });
            }),

            // Computed fields
            'computed_fields' => [
                'directory_name' => $this->getDirectoryName(),
                'usage_percentage' => $this->getLatestUsagePercentage(),
                'free_percentage' => $this->getLatestFreePercentage(),
                'is_linux_system_dir' => $this->isLinuxSystemDirectory(),
                'is_windows_system_dir' => $this->isWindowsSystemDirectory(),
                'formatted_used_space' => $this->getFormattedUsedSpace(),
                'formatted_total_space' => $this->getFormattedTotalSpace(),
                'formatted_available_space' => $this->getFormattedAvailableSpace(),
                'status' => $this->getDirectoryStatus(),
                // 'days_since_created' => $this->created_at->diffInDays(now()),
                'days_since_created' => $this->created_at ? $this->created_at->diffInDays(now()) : null,
                'monitoring_duration' => $this->getFormattedMonitoringDuration()
            ],

            // Analysis and insights
            'analysis' => [
                'health_status' => $this->getHealthStatus(),
                'risk_level' => $this->getRiskLevel(),
                'recommendations' => $this->getRecommendations(),
                'last_metric_age_hours' => $this->getLastMetricAgeHours(),
                'trend' => $this->getUsageTrend()
            ]
        ];
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get latest free space percentage
    /// </summary>
    /// <returns>float|null</returns>
    private function getLatestFreePercentage(): ?float
    {
        $usagePercentage = $this->getLatestUsagePercentage();
        return $usagePercentage !== null ? round(100 - $usagePercentage, 2) : null;
    }

    /// <summary>
    /// Get formatted used space
    /// </summary>
    /// <returns>string|null</returns>
    private function getFormattedUsedSpace(): ?string
    {
        if (!$this->latestMetric) {
            return null;
        }
        return $this->formatBytes($this->latestMetric->used_space);
    }

    /// <summary>
    /// Get formatted total space
    /// </summary>
    /// <returns>string|null</returns>
    private function getFormattedTotalSpace(): ?string
    {
        if (!$this->latestMetric) {
            return null;
        }
        return $this->formatBytes($this->latestMetric->total_space);
    }

    /// <summary>
    /// Get formatted available space
    /// </summary>
    /// <returns>string|null</returns>
    private function getFormattedAvailableSpace(): ?string
    {
        if (!$this->latestMetric) {
            return null;
        }
        return $this->formatBytes($this->latestMetric->available_space);
    }

    /// <summary>
    /// Get directory status
    /// </summary>
    /// <returns>string</returns>
    private function getDirectoryStatus(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if (!$this->latestMetric) {
            return 'no_data';
        }

        $ageHours = $this->getLastMetricAgeHours();
        if ($ageHours > 24) {
            return 'stale_data';
        }

        $usagePercentage = $this->getLatestUsagePercentage();
        if ($usagePercentage === null) {
            return 'unknown';
        }

        if ($usagePercentage >= 90) {
            return 'critical';
        } elseif ($usagePercentage >= 80) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /// <summary>
    /// Get formatted monitoring duration
    /// </summary>
    /// <returns>string</returns>
    private function getFormattedMonitoringDuration(): string
    {
        // $days = $this->created_at->diffInDays(now());
        $days =  $this->created_at ? $this->created_at->diffInDays(now()) : null;

        if ($days < 1) {
            // $hours = $this->created_at->diffInHours(now());
            $hours = $this->created_at ? $this->created_at->diffInHours(now()) : null;
            return $hours . ' hours';
        } elseif ($days < 7) {
            return $days . ' days';
        } elseif ($days < 30) {
            $weeks = floor($days / 7);
            return $weeks . ' weeks';
        } elseif ($days < 365) {
            $months = floor($days / 30);
            return $months . ' months';
        } else {
            $years = floor($days / 365);
            return $years . ' years';
        }
    }

    /// <summary>
    /// Get health status based on usage and data freshness
    /// </summary>
    /// <returns>string</returns>
    private function getHealthStatus(): string
    {
        if (!$this->is_active) {
            return 'unknown';
        }

        if (!$this->latestMetric) {
            return 'unknown';
        }

        $ageHours = $this->getLastMetricAgeHours();
        if ($ageHours > 48) {
            return 'unknown';
        }

        $usagePercentage = $this->getLatestUsagePercentage();
        if ($usagePercentage === null) {
            return 'unknown';
        }

        if ($usagePercentage >= 95) {
            return 'critical';
        } elseif ($usagePercentage >= 85) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /// <summary>
    /// Get risk level based on directory type and usage
    /// </summary>
    /// <returns>string</returns>
    private function getRiskLevel(): string
    {
        if (!$this->latestMetric) {
            return 'low';
        }

        $usagePercentage = $this->getLatestUsagePercentage();
        $isSystemDir = $this->isLinuxSystemDirectory() || $this->isWindowsSystemDirectory();

        if ($usagePercentage >= 90 && $isSystemDir) {
            return 'high';
        } elseif ($usagePercentage >= 80 && $isSystemDir) {
            return 'medium';
        } elseif ($usagePercentage >= 95) {
            return 'high';
        } elseif ($usagePercentage >= 85) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /// <summary>
    /// Get recommendations based on directory analysis
    /// </summary>
    /// <returns>array</returns>
    private function getRecommendations(): array
    {
        $recommendations = [];

        if (!$this->is_active) {
            $recommendations[] = 'Directory monitoring is disabled - consider enabling if needed';
            return $recommendations;
        }

        if (!$this->latestMetric) {
            $recommendations[] = 'No metrics available - check agent connectivity';
            return $recommendations;
        }

        $usagePercentage = $this->getLatestUsagePercentage();
        $ageHours = $this->getLastMetricAgeHours();

        // Data freshness recommendations
        if ($ageHours > 24) {
            $recommendations[] = 'Metrics are stale - check agent status';
        }

        // Usage recommendations
        if ($usagePercentage >= 95) {
            $recommendations[] = 'Critical disk usage - immediate cleanup required';
            $recommendations[] = 'Consider archiving old files or expanding storage';
        } elseif ($usagePercentage >= 85) {
            $recommendations[] = 'High disk usage - plan cleanup or storage expansion';
        } elseif ($usagePercentage >= 80) {
            $recommendations[] = 'Monitor closely - usage approaching threshold';
        }

        // System directory specific recommendations
        if ($this->isLinuxSystemDirectory() && $usagePercentage >= 80) {
            $recommendations[] = 'System directory with high usage - check log rotation';
        }

        // File count recommendations
        if ($this->latestMetric && $this->latestMetric->file_count > 100000) {
            $recommendations[] = 'High file count - consider cleanup or archiving';
        }

        return empty($recommendations) ? ['Directory usage looks healthy'] : $recommendations;
    }

    /// <summary>
    /// Get last metric age in hours
    /// </summary>
    /// <returns>int|null</returns>
    private function getLastMetricAgeHours(): ?int
    {
        if (!$this->latestMetric) {
            return null;
        }
        // return $this->latestMetric->timestamp->diffInHours(now());
        return $this->latestMetric ? $this->latestMetric->timestamp->diffInHours(now()) : null;
    }

    /// <summary>
    /// Get usage trend based on recent metrics
    /// </summary>
    /// <returns>string</returns>
    private function getUsageTrend(): string
    {
        if (!$this->relationLoaded('directoryMetrics') || $this->directoryMetrics->count() < 2) {
            return 'unknown';
        }

        $metrics = $this->directoryMetrics->sortBy('timestamp');
        $first = $metrics->first();
        $last = $metrics->last();

        if (!$first || !$last || $first->total_space == 0 || $last->total_space == 0) {
            return 'unknown';
        }

        $firstUsage = ($first->used_space / $first->total_space) * 100;
        $lastUsage = ($last->used_space / $last->total_space) * 100;
        $difference = $lastUsage - $firstUsage;

        if (abs($difference) < 2) {
            return 'stable';
        } elseif ($difference > 0) {
            return 'growing';
        } else {
            return 'shrinking';
        }
    }

    /// <summary>
    /// Format bytes to human readable format
    /// </summary>
    /// <param>int $bytes</param>
    /// <returns>string</returns>
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /// <summary>
    /// Add custom headers to the response
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <param name="response">HTTP response</param>
    /// <returns>void</returns>
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'MonitoredDirectory');
    }

    #endregion
}