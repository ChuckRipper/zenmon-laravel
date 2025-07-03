<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="MetricTypeResource",
 *      type="object",
 *      title="MetricTypeResource",
 *      description="Metric Type API Resource with computed fields",
 *      @OA\Property(property="metric_type_id", type="integer", example=1),
 *      @OA\Property(property="metric_name", type="string", example="CPU"),
 *      @OA\Property(property="unit", type="string", example="%"),
 *      @OA\Property(property="description", type="string", example="CPU usage percentage"),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(
 *          property="statistics",
 *          type="object",
 *          @OA\Property(property="total_metrics", type="integer"),
 *          @OA\Property(property="metrics_last_24h", type="integer"),
 *          @OA\Property(property="metrics_last_7d", type="integer"),
 *          @OA\Property(property="unique_hosts", type="integer"),
 *          @OA\Property(property="alert_thresholds_count", type="integer"),
 *          @OA\Property(property="active_alerts_count", type="integer")
 *      ),
 *      @OA\Property(
 *          property="computed_fields",
 *          type="object",
 *          @OA\Property(property="category", type="string"),
 *          @OA\Property(property="is_system_metric", type="boolean"),
 *          @OA\Property(property="is_custom_metric", type="boolean"),
 *          @OA\Property(property="unit_category", type="string"),
 *          @OA\Property(property="data_freshness", type="string"),
 *          @OA\Property(property="usage_intensity", type="string"),
 *          @OA\Property(property="last_metric_age_hours", type="integer", nullable=true)
 *      ),
 *      @OA\Property(
 *          property="analysis",
 *          type="object",
 *          @OA\Property(property="health_status", type="string", enum={"healthy", "inactive", "stale", "problematic"}),
 *          @OA\Property(property="monitoring_quality", type="string", enum={"excellent", "good", "poor", "none"}),
 *          @OA\Property(property="recommendations", type="array", @OA\Items(type="string")),
 *          @OA\Property(property="trends", type="object",
 *              @OA\Property(property="metrics_growth_7d", type="string"),
 *              @OA\Property(property="host_adoption", type="string")
 *          )
 *      )
 * )
 */
class MetricTypeResource extends JsonResource
{
    #region Properties
    /// <summary>
    /// Additional data to include when transforming the resource
    /// </summary>
    public static $wrap = null;
    #endregion

    #region Methods

    /// <summary>
    /// Transform the resource into an array.
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <returns>array<string, mixed> Transformed resource array</returns>
    public function toArray(Request $request): array
    {
        return [
            // Basic metric type information
            'metric_type_id' => $this->metric_type_id,
            'metric_name' => $this->metric_name,
            'unit' => $this->unit,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Statistics (when counts are loaded)
            'statistics' => [
                'total_metrics' => $this->whenCounted('metrics', $this->metrics_count ?? 0),
                'metrics_last_24h' => $this->when($this->relationLoaded('metrics'), function () {
                    return $this->metrics->where('timestamp', '>=', now()->subHours(24))->count();
                }),
                'metrics_last_7d' => $this->when($this->relationLoaded('metrics'), function () {
                    return $this->metrics->where('timestamp', '>=', now()->subDays(7))->count();
                }),
                'unique_hosts' => $this->when($this->relationLoaded('metrics'), function () {
                    return $this->metrics->pluck('host_id')->unique()->count();
                }),
                'alert_thresholds_count' => $this->whenCounted('alertThresholds', $this->alert_thresholds_count ?? 0),
                'active_alerts_count' => $this->when($this->relationLoaded('alerts'), function () {
                    return $this->alerts->where('status', 'Active')->count();
                })
            ],

            // Recent metrics (when loaded)
            'recent_metrics' => $this->when($this->relationLoaded('metrics') && $this->metrics->isNotEmpty(), function () {
                return $this->metrics->take(5)->map(function ($metric) {
                    return [
                        'metric_id' => $metric->metric_id,
                        'value' => $metric->value,
                        'timestamp' => $metric->timestamp,
                        'host' => [
                            'host_id' => $metric->host->host_id ?? null,
                            'host_name' => $metric->host->host_name ?? 'Unknown',
                            'ip_address' => $metric->host->ip_address ?? null
                        ]
                    ];
                });
            }),

            // Alert thresholds (when loaded)
            'alert_thresholds' => $this->when($this->relationLoaded('alertThresholds'), function () {
                return $this->alertThresholds->map(function ($threshold) {
                    return [
                        'threshold_id' => $threshold->threshold_id,
                        'warning_threshold' => $threshold->warning_threshold,
                        'critical_threshold' => $threshold->critical_threshold,
                        'is_active' => $threshold->is_active,
                        'host' => $threshold->host ? [
                            'host_id' => $threshold->host->host_id,
                            'host_name' => $threshold->host->host_name
                        ] : null
                    ];
                });
            }),

            // Computed fields
            'computed_fields' => [
                'category' => $this->getMetricCategory(),
                'is_system_metric' => $this->isSystemMetric(),
                'is_custom_metric' => $this->isCustomMetric(),
                'unit_category' => $this->getUnitCategory(),
                'data_freshness' => $this->getDataFreshness(),
                'usage_intensity' => $this->getUsageIntensity(),
                'last_metric_age_hours' => $this->getLastMetricAgeHours(),
                'monitoring_scope' => $this->getMonitoringScope()
            ],

            // Analysis and insights
            'analysis' => [
                'health_status' => $this->getHealthStatus(),
                'monitoring_quality' => $this->getMonitoringQuality(),
                'recommendations' => $this->getRecommendations(),
                'trends' => $this->getTrends()
            ]
        ];
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get metric category based on name
    /// </summary>
    /// <returns>string</returns>
    private function getMetricCategory(): string
    {
        $name = strtolower($this->metric_name);
        
        if (in_array($name, ['cpu', 'processor', 'cpu_usage'])) {
            return 'Performance';
        } elseif (in_array($name, ['ram', 'memory', 'mem', 'memory_usage'])) {
            return 'Memory';
        } elseif (in_array($name, ['disk', 'storage', 'disk_usage', 'filesystem'])) {
            return 'Storage';
        } elseif (in_array($name, ['network', 'net', 'bandwidth', 'ping', 'latency'])) {
            return 'Network';
        } elseif (in_array($name, ['temperature', 'temp', 'fan', 'power'])) {
            return 'Hardware';
        } elseif (strpos($name, 'process') !== false || strpos($name, 'service') !== false) {
            return 'Application';
        } else {
            return 'Custom';
        }
    }

    /// <summary>
    /// Check if this is a system metric
    /// </summary>
    /// <returns>bool</returns>
    private function isSystemMetric(): bool
    {
        $systemMetrics = ['cpu', 'ram', 'memory', 'disk', 'network', 'temperature', 'load'];
        $name = strtolower($this->metric_name);
        
        foreach ($systemMetrics as $systemMetric) {
            if (strpos($name, $systemMetric) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /// <summary>
    /// Check if this is a custom metric
    /// </summary>
    /// <returns>bool</returns>
    private function isCustomMetric(): bool
    {
        return !$this->isSystemMetric();
    }

    /// <summary>
    /// Get unit category
    /// </summary>
    /// <returns>string</returns>
    private function getUnitCategory(): string
    {
        $unit = strtolower($this->unit);
        
        if (in_array($unit, ['%', 'percent', 'percentage'])) {
            return 'Percentage';
        } elseif (in_array($unit, ['ms', 'milliseconds', 's', 'seconds', 'min', 'minutes', 'h', 'hours'])) {
            return 'Time';
        } elseif (in_array($unit, ['kb', 'mb', 'gb', 'tb', 'bytes'])) {
            return 'Data Size';
        } elseif (in_array($unit, ['kb/s', 'mb/s', 'gb/s', 'bps', 'mbps', 'gbps'])) {
            return 'Data Rate';
        } elseif (in_array($unit, ['°c', 'celsius', '°f', 'fahrenheit', 'kelvin'])) {
            return 'Temperature';
        } elseif (in_array($unit, ['count', 'number', 'num', '#'])) {
            return 'Count';
        } elseif (in_array($unit, ['rpm', 'hz', 'khz', 'mhz', 'ghz'])) {
            return 'Frequency';
        } else {
            return 'Other';
        }
    }

    /// <summary>
    /// Get data freshness status
    /// </summary>
    /// <returns>string</returns>
    private function getDataFreshness(): string
    {
        $lastMetricHours = $this->getLastMetricAgeHours();
        
        if ($lastMetricHours === null) {
            return 'no_data';
        } elseif ($lastMetricHours <= 1) {
            return 'very_fresh';
        } elseif ($lastMetricHours <= 6) {
            return 'fresh';
        } elseif ($lastMetricHours <= 24) {
            return 'recent';
        } elseif ($lastMetricHours <= 168) { // 1 week
            return 'stale';
        } else {
            return 'very_stale';
        }
    }

    /// <summary>
    /// Get usage intensity based on metrics count
    /// </summary>
    /// <returns>string</returns>
    private function getUsageIntensity(): string
    {
        $totalMetrics = $this->metrics_count ?? 0;
        $metricsLast24h = 0;
        
        if ($this->relationLoaded('metrics')) {
            $metricsLast24h = $this->metrics->where('timestamp', '>=', now()->subHours(24))->count();
        }
        
        if ($totalMetrics == 0) {
            return 'unused';
        } elseif ($metricsLast24h >= 100) {
            return 'very_high';
        } elseif ($metricsLast24h >= 50) {
            return 'high';
        } elseif ($metricsLast24h >= 20) {
            return 'moderate';
        } elseif ($metricsLast24h >= 5) {
            return 'low';
        } else {
            return 'very_low';
        }
    }

    /// <summary>
    /// Get last metric age in hours
    /// </summary>
    /// <returns>int|null</returns>
    private function getLastMetricAgeHours(): ?int
    {
        if (!$this->relationLoaded('metrics') || $this->metrics->isEmpty()) {
            return null;
        }
        
        $latestMetric = $this->metrics->sortByDesc('timestamp')->first();
        return $latestMetric ? $latestMetric->timestamp->diffInHours(now()) : null;
    }

    /// <summary>
    /// Get monitoring scope
    /// </summary>
    /// <returns>string</returns>
    private function getMonitoringScope(): string
    {
        if (!$this->relationLoaded('metrics')) {
            return 'unknown';
        }
        
        $uniqueHosts = $this->metrics->pluck('host_id')->unique()->count();
        
        if ($uniqueHosts == 0) {
            return 'none';
        } elseif ($uniqueHosts == 1) {
            return 'single_host';
        } elseif ($uniqueHosts <= 5) {
            return 'few_hosts';
        } elseif ($uniqueHosts <= 20) {
            return 'multiple_hosts';
        } else {
            return 'many_hosts';
        }
    }

    /// <summary>
    /// Get health status
    /// </summary>
    /// <returns>string</returns>
    private function getHealthStatus(): string
    {
        $lastMetricHours = $this->getLastMetricAgeHours();
        $totalMetrics = $this->metrics_count ?? 0;
        
        if ($totalMetrics == 0) {
            return 'inactive';
        } elseif ($lastMetricHours === null || $lastMetricHours > 168) { // 1 week
            return 'stale';
        } elseif ($lastMetricHours <= 24) {
            return 'healthy';
        } else {
            return 'problematic';
        }
    }

    /// <summary>
    /// Get monitoring quality assessment
    /// </summary>
    /// <returns>string</returns>
    private function getMonitoringQuality(): string
    {
        $totalMetrics = $this->metrics_count ?? 0;
        $thresholds = $this->alert_thresholds_count ?? 0;
        $lastMetricHours = $this->getLastMetricAgeHours();
        
        if ($totalMetrics == 0) {
            return 'none';
        }
        
        $score = 0;
        
        // Recent data (+2 points)
        if ($lastMetricHours !== null && $lastMetricHours <= 6) {
            $score += 2;
        } elseif ($lastMetricHours !== null && $lastMetricHours <= 24) {
            $score += 1;
        }
        
        // Good metrics volume (+1 point)
        if ($totalMetrics >= 100) {
            $score += 1;
        }
        
        // Has alert thresholds (+1 point)
        if ($thresholds > 0) {
            $score += 1;
        }
        
        // Multiple hosts (+1 point)
        if ($this->relationLoaded('metrics')) {
            $uniqueHosts = $this->metrics->pluck('host_id')->unique()->count();
            if ($uniqueHosts > 1) {
                $score += 1;
            }
        }
        
        if ($score >= 4) {
            return 'excellent';
        } elseif ($score >= 2) {
            return 'good';
        } else {
            return 'poor';
        }
    }

    /// <summary>
    /// Get recommendations for metric type
    /// </summary>
    /// <returns>array</returns>
    private function getRecommendations(): array
    {
        $recommendations = [];
        $totalMetrics = $this->metrics_count ?? 0;
        $thresholds = $this->alert_thresholds_count ?? 0;
        $lastMetricHours = $this->getLastMetricAgeHours();
        
        // No data recommendations
        if ($totalMetrics == 0) {
            $recommendations[] = 'No metrics collected yet - verify agent configuration';
            return $recommendations;
        }
        
        // Stale data recommendations
        if ($lastMetricHours !== null && $lastMetricHours > 24) {
            $recommendations[] = 'Data is stale - check agent connectivity and collection settings';
        }
        
        // Alert threshold recommendations
        if ($thresholds == 0 && $this->isSystemMetric()) {
            $recommendations[] = 'System metric without alert thresholds - consider adding monitoring alerts';
        }
        
        // Usage recommendations
        $intensity = $this->getUsageIntensity();
        if ($intensity === 'very_low' && $this->isSystemMetric()) {
            $recommendations[] = 'Low data collection frequency - consider increasing monitoring interval';
        } elseif ($intensity === 'very_high') {
            $recommendations[] = 'Very high data volume - consider optimizing collection frequency';
        }
        
        // Monitoring scope recommendations
        $scope = $this->getMonitoringScope();
        if ($scope === 'single_host' && $this->isSystemMetric()) {
            $recommendations[] = 'Only monitored on one host - consider expanding to more hosts';
        }
        
        // Custom metric recommendations
        if ($this->isCustomMetric() && empty($this->description)) {
            $recommendations[] = 'Custom metric without description - add documentation';
        }
        
        return empty($recommendations) ? ['Metric type configuration looks good'] : $recommendations;
    }

    /// <summary>
    /// Get trends analysis
    /// </summary>
    /// <returns>array</returns>
    private function getTrends(): array
    {
        $trends = [
            'metrics_growth_7d' => 'stable',
            'host_adoption' => 'stable'
        ];
        
        if (!$this->relationLoaded('metrics')) {
            return $trends;
        }
        
        // Metrics growth trend
        $metricsLast7d = $this->metrics->where('timestamp', '>=', now()->subDays(7))->count();
        $metricsPrevious7d = $this->metrics->whereBetween('timestamp', [
            now()->subDays(14),
            now()->subDays(7)
        ])->count();
        
        if ($metricsPrevious7d > 0) {
            $growthRate = (($metricsLast7d - $metricsPrevious7d) / $metricsPrevious7d) * 100;
            
            if ($growthRate > 20) {
                $trends['metrics_growth_7d'] = 'growing';
            } elseif ($growthRate < -20) {
                $trends['metrics_growth_7d'] = 'declining';
            }
        } elseif ($metricsLast7d > 0) {
            $trends['metrics_growth_7d'] = 'new';
        }
        
        // Host adoption trend
        $uniqueHosts = $this->metrics->pluck('host_id')->unique()->count();
        if ($uniqueHosts > 5) {
            $trends['host_adoption'] = 'widespread';
        } elseif ($uniqueHosts > 1) {
            $trends['host_adoption'] = 'expanding';
        } else {
            $trends['host_adoption'] = 'limited';
        }
        
        return $trends;
    }

    /// <summary>
    /// Customize the outgoing response for the resource.
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <param name="response">HTTP response</param>
    /// <returns>void</returns>
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'MetricType');
    }

    #endregion
}