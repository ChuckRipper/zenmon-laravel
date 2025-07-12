<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="DirectoryMetricResource",
 *      type="object",
 *      title="DirectoryMetricResource",
 *      description="Directory Metric API Resource with computed fields",
 *      @OA\Property(property="directory_metric_id", type="integer", example=1),
 *      @OA\Property(property="directory_id", type="integer", example=1),
 *      @OA\Property(property="used_space", type="integer", example=1073741824),
 *      @OA\Property(property="total_space", type="integer", example=10737418240),
 *      @OA\Property(property="available_space", type="integer", example=9663676416),
 *      @OA\Property(property="file_count", type="integer", example=1542),
 *      @OA\Property(property="timestamp", type="string", format="date-time"),
 *      @OA\Property(
 *          property="monitored_directory",
 *          type="object",
 *          @OA\Property(property="directory_id", type="integer"),
 *          @OA\Property(property="directory_path", type="string"),
 *          @OA\Property(property="host_name", type="string"),
 *          @OA\Property(property="host_id", type="integer"),
 *          @OA\Property(property="is_active", type="boolean")
 *      ),
 *      @OA\Property(
 *          property="computed_fields",
 *          type="object",
 *          @OA\Property(property="usage_percentage", type="number", format="float"),
 *          @OA\Property(property="free_percentage", type="number", format="float"),
 *          @OA\Property(property="formatted_used_space", type="string"),
 *          @OA\Property(property="formatted_total_space", type="string"),
 *          @OA\Property(property="formatted_available_space", type="string"),
 *          @OA\Property(property="usage_status", type="string"),
 *          @OA\Property(property="hours_ago", type="integer"),
 *          @OA\Property(property="time_ago_human", type="string"),
 *          @OA\Property(property="space_efficiency", type="number", format="float")
 *      ),
 *      @OA\Property(
 *          property="analysis",
 *          type="object",
 *          @OA\Property(property="health_status", type="string", enum={"healthy", "warning", "critical", "unknown"}),
 *          @OA\Property(property="risk_level", type="string", enum={"low", "medium", "high"}),
 *          @OA\Property(property="recommendations", type="array", @OA\Items(type="string")),
 *          @OA\Property(property="data_freshness", type="string", enum={"fresh", "stale", "very_stale"}),
 *          @OA\Property(property="capacity_projection", type="object",
 *              @OA\Property(property="days_until_full", type="integer", nullable=true),
 *              @OA\Property(property="growth_rate_per_day", type="number", format="float", nullable=true)
 *          )
 *      )
 * )
 */
class DirectoryMetricResource extends JsonResource
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
    /// Transform directory metric resource into array with computed fields and analysis
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>array</returns>
    public function toArray(Request $request): array
    {
        return [
            // Basic metric information
            'directory_metric_id' => $this->directory_metric_id,
            'directory_id' => $this->monitoredDirectory->directory_id ?? null,
            'used_space' => $this->used_space,
            'total_space' => $this->total_space,
            'available_space' => $this->available_space,
            'file_count' => $this->file_count,
            'timestamp' => $this->timestamp,

            // Related directory and host information
            'monitored_directory' => $this->when($this->relationLoaded('monitoredDirectory') && $this->monitoredDirectory, function () {
                return [
                    'directory_id' => $this->monitoredDirectory->directory_id,
                    'directory_path' => $this->monitoredDirectory->directory_path,
                    'host_id' => $this->monitoredDirectory->host_id,
                    'is_active' => $this->monitoredDirectory->is_active,
                    'host_name' => $this->monitoredDirectory->host->host_name ?? 'Unknown',
                    'host_ip' => $this->monitoredDirectory->host->ip_address ?? null,
                    'operating_system' => $this->monitoredDirectory->host->operating_system ?? null
                ];
            }),

            // Computed fields
            'computed_fields' => [
                'usage_percentage' => $this->getUsagePercentage(),
                'free_percentage' => $this->getFreePercentage(),
                'formatted_used_space' => $this->formatBytes($this->used_space),
                'formatted_total_space' => $this->formatBytes($this->total_space),
                'formatted_available_space' => $this->formatBytes($this->available_space),
                'usage_status' => $this->getUsageStatus(),
                // 'hours_ago' => $this->timestamp->diffInHours(now()),
                'hours_ago' => $this->timestamp ? $this->timestamp->diffInHours(now()) : null,
                'time_ago_human' => $this->getTimeAgoHuman(),
                'space_efficiency' => $this->getSpaceEfficiency(),
                'files_per_mb' => $this->getFilesPerMB()
            ],

            // Analysis and insights
            'analysis' => [
                'health_status' => $this->getHealthStatus(),
                'risk_level' => $this->getRiskLevel(),
                'recommendations' => $this->getRecommendations(),
                'data_freshness' => $this->getDataFreshness(),
                'capacity_projection' => $this->getCapacityProjection()
            ]
        ];
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get usage percentage
    /// </summary>
    /// <returns>float</returns>
    private function getUsagePercentage(): float
    {
        if ($this->total_space <= 0) {
            return 0;
        }
        return round(($this->used_space / $this->total_space) * 100, 2);
    }

    /// <summary>
    /// Get free space percentage
    /// </summary>
    /// <returns>float</returns>
    private function getFreePercentage(): float
    {
        return round(100 - $this->getUsagePercentage(), 2);
    }

    /// <summary>
    /// Get usage status based on percentage
    /// </summary>
    /// <returns>string</returns>
    private function getUsageStatus(): string
    {
        $usage = $this->getUsagePercentage();
        
        if ($usage >= 95) {
            return 'critical';
        } elseif ($usage >= 85) {
            return 'warning';
        } elseif ($usage >= 75) {
            return 'high';
        } elseif ($usage >= 50) {
            return 'moderate';
        } else {
            return 'low';
        }
    }

    /// <summary>
    /// Get human readable time ago
    /// </summary>
    /// <returns>string</returns>
    private function getTimeAgoHuman(): string
    {
        // $hours = $this->timestamp->diffInHours(now());
        $hours = $this->timestamp ? $this->timestamp->diffInHours(now()) : 0;
        
        if ($hours < 1) {
            // $minutes = $this->timestamp->diffInMinutes(now());
            $minutes = $this->timestamp ? $this->timestamp->diffInMinutes(now()) : 0;
            return $minutes . ' minutes ago';
        } elseif ($hours < 24) {
            return $hours . ' hours ago';
        } else {
            $days = $this->timestamp->diffInDays(now());
            return $days . ' days ago';
        }
    }

    /// <summary>
    /// Get space efficiency (available space / total space ratio)
    /// </summary>
    /// <returns>float</returns>
    private function getSpaceEfficiency(): float
    {
        if ($this->total_space <= 0) {
            return 0;
        }
        return round(($this->available_space / $this->total_space) * 100, 2);
    }

    /// <summary>
    /// Get files per megabyte
    /// </summary>
    /// <returns>float</returns>
    private function getFilesPerMB(): float
    {
        $usedSpaceMB = $this->used_space / (1024 * 1024);
        if ($usedSpaceMB <= 0) {
            return 0;
        }
        return round($this->file_count / $usedSpaceMB, 2);
    }

    /// <summary>
    /// Get health status based on usage and data age
    /// </summary>
    /// <returns>string</returns>
    private function getHealthStatus(): string
    {
        $usage = $this->getUsagePercentage();
        // $hoursAgo = $this->timestamp->diffInHours(now());
        $hoursAgo = $this->timestamp ? $this->timestamp->diffInHours(now()) : 0;
        
        // If data is very old, status is unknown
        if ($hoursAgo > 48) {
            return 'unknown';
        }
        
        if ($usage >= 95) {
            return 'critical';
        } elseif ($usage >= 85) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /// <summary>
    /// Get risk level based on various factors
    /// </summary>
    /// <returns>string</returns>
    private function getRiskLevel(): string
    {
        $usage = $this->getUsagePercentage();
        // $hoursAgo = $this->timestamp->diffInHours(now());
        $hoursAgo = $this->timestamp ? $this->timestamp->diffInHours(now()) : 0;
        
        // Check if this is a system directory
        $isSystemDir = false;
        if ($this->relationLoaded('monitoredDirectory')) {
            // $path = strtolower($this->monitoredDirectory->directory_path);
            $path = $this->monitoredDirectory ? strtolower($this->monitoredDirectory->directory_path) : '';
            $isSystemDir = in_array($path, ['/root', '/var', '/tmp', '/usr', '/opt']) || 
                          str_starts_with($path, 'c:\\');
        }
        
        // High risk conditions
        if ($usage >= 95 && $isSystemDir) {
            return 'high';
        } elseif ($usage >= 90) {
            return 'high';
        } elseif ($usage >= 80 && $isSystemDir) {
            return 'medium';
        } elseif ($usage >= 75) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /// <summary>
    /// Get recommendations based on analysis
    /// </summary>
    /// <returns>array</returns>
    private function getRecommendations(): array
    {
        $recommendations = [];
        $usage = $this->getUsagePercentage();
        // $hoursAgo = $this->timestamp->diffInHours(now());
        $hoursAgo = $this->timestamp ? $this->timestamp->diffInHours(now()) : 0;

        // Data freshness recommendations
        if ($hoursAgo > 24) {
            $recommendations[] = 'Data is stale - check monitoring agent connectivity';
        }
        
        // Usage recommendations
        if ($usage >= 95) {
            $recommendations[] = 'URGENT: Directory is nearly full - immediate cleanup required';
            $recommendations[] = 'Consider archiving old files or expanding storage capacity';
        } elseif ($usage >= 85) {
            $recommendations[] = 'High usage detected - plan cleanup or storage expansion';
            $recommendations[] = 'Review file retention policies';
        } elseif ($usage >= 75) {
            $recommendations[] = 'Monitor closely - usage is approaching warning threshold';
        }
        
        // File count recommendations
        if ($this->file_count > 100000) {
            $recommendations[] = 'High file count detected - consider file consolidation';
        }
        
        // System directory specific recommendations
        if ($this->relationLoaded('monitoredDirectory')) {
            // $path = strtolower($this->monitoredDirectory->directory_path);
            $path = $this->monitoredDirectory ? strtolower($this->monitoredDirectory->directory_path) : '';
            if (in_array($path, ['/var/log', '/tmp']) && $usage >= 80) {
                $recommendations[] = 'System directory with high usage - check log rotation settings';
            }
        }
        
        // Space efficiency recommendations
        $efficiency = $this->getSpaceEfficiency();
        if ($efficiency < 20) {
            $recommendations[] = 'Low available space - consider cleanup or storage expansion';
        }
        
        return empty($recommendations) ? ['Directory usage appears normal'] : $recommendations;
    }

    /// <summary>
    /// Get data freshness status
    /// </summary>
    /// <returns>string</returns>
    private function getDataFreshness(): string
    {
        // $hoursAgo = $this->timestamp->diffInHours(now());
        $hoursAgo = $this->timestamp ? $this->timestamp->diffInHours(now()) : 0;

        if ($hoursAgo <= 6) {
            return 'fresh';
        } elseif ($hoursAgo <= 24) {
            return 'stale';
        } else {
            return 'very_stale';
        }
    }

    /// <summary>
    /// Get capacity projection (simplified)
    /// </summary>
    /// <returns>array</returns>
    private function getCapacityProjection(): array
    {
        // This is a simplified projection - in real implementation,
        // you'd want to analyze historical data to calculate growth trends
        $usage = $this->getUsagePercentage();
        
        if ($usage >= 95) {
            return [
                'days_until_full' => 0,
                'growth_rate_per_day' => null,
                'projection_confidence' => 'high'
            ];
        } elseif ($usage >= 85) {
            // Simplified projection - assumes linear growth
            $remainingSpace = 100 - $usage;
            $estimatedDays = max(1, round($remainingSpace * 7)); // Rough estimate
            
            return [
                'days_until_full' => $estimatedDays,
                'growth_rate_per_day' => round((100 - $usage) / $estimatedDays, 2),
                'projection_confidence' => 'medium'
            ];
        } else {
            return [
                'days_until_full' => null,
                'growth_rate_per_day' => null,
                'projection_confidence' => 'low'
            ];
        }
    }

    /// <summary>
    /// Format bytes to human readable format
    /// </summary>
    /// <param>int $bytes</param>
    /// <returns>string</returns>
    private function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /// <summary>
    /// Add custom headers to the response
    /// </summary>
    /// <param>Request $request</param>
    /// <param>$response</param>
    /// <returns>void</returns>
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'DirectoryMetric');
    }

    #endregion
}