<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="MetricResponse",
 *      type="object",
 *      title="MetricResponse",
 *      description="Metric API Resource with monitoring data and analysis",
 *      @OA\Property(property="metric_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="metric_type_id", type="integer", example=1),
 *      @OA\Property(property="value", type="number", format="float", example=75.5),
 *      @OA\Property(property="timestamp", type="string", format="date-time"),
 *      @OA\Property(property="additional_info", type="object", nullable=true),
 *      @OA\Property(property="host", type="object",
 *          @OA\Property(property="host_id", type="integer", example=1),
 *          @OA\Property(property="host_name", type="string", example="web-server-01"),
 *          @OA\Property(property="ip_address", type="string", example="192.168.1.100")
 *      ),
 *      @OA\Property(property="metric_type", type="object",
 *          @OA\Property(property="metric_type_id", type="integer", example=1),
 *          @OA\Property(property="metric_name", type="string", example="CPU"),
 *          @OA\Property(property="unit", type="string", example="%"),
 *          @OA\Property(property="description", type="string", example="CPU usage percentage")
 *      ),
 *      @OA\Property(property="analysis", type="object",
 *          @OA\Property(property="threshold_status", type="string", enum={"normal", "warning", "critical"}),
 *          @OA\Property(property="age_hours", type="number", format="float", example=0.5),
 *          @OA\Property(property="trend_indicator", type="string", enum={"stable", "increasing", "decreasing"}),
 *          @OA\Property(property="data_quality", type="string", enum={"excellent", "good", "poor"})
 *      )
 * )
 */
class MetricResource extends JsonResource
{
    #region Properties

    /// <summary>
    /// Transform the resource into an array.
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <returns>array<string, mixed> Transformed resource array</returns>
    public function toArray(Request $request): array
    {
        return [
            // Basic metric information
            'metric_id' => $this->metric_id,
            'host_id' => $this->host_id,
            'metric_type_id' => $this->metric_type_id,
            'value' => (float) $this->value,
            'timestamp' => $this->timestamp->toISOString(),
            'additional_info' => $this->additional_info,
            // 'created_at' => $this->created_at->toISOString(),

            // Related entities when loaded
            'host' => $this->when($this->relationLoaded('host'), [
                'host_id' => $this->host->host_id,
                'host_name' => $this->host->host_name,
                'ip_address' => $this->host->ip_address,
                'operating_system' => $this->host->operating_system
            ]),

            'metric_type' => $this->when($this->relationLoaded('metricType'), [
                'metric_type_id' => $this->metricType->metric_type_id,
                'metric_name' => $this->metricType->metric_name,
                'unit' => $this->metricType->unit,
                'description' => $this->metricType->description
            ]),

            // Analysis and computed fields
            'analysis' => [
                'threshold_status' => $this->getThresholdStatus(),
                'age_hours' => $this->getAgeHours(),
                'trend_indicator' => $this->getTrendIndicator(),
                'data_quality' => $this->getDataQuality(),
                'is_recent' => $this->isRecent()
            ],

            // Context when multiple metrics are available
            'context' => $this->when($this->relationLoaded('host.metrics'), function () {
                return [
                    'recent_average' => $this->getRecentAverage(),
                    'daily_min' => $this->getDailyMin(),
                    'daily_max' => $this->getDailyMax(),
                    'variance_from_average' => $this->getVarianceFromAverage()
                ];
            })
        ];
    }

    #endregion

    #region Methods

    /// <summary>
    /// Get additional data that should be returned with the resource array.
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <returns>array<string, mixed> Additional response data</returns>
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => now()->toISOString()
            ]
        ];
    }

    /// <summary>
    /// Get threshold status based on alert thresholds
    /// </summary>
    /// <returns>string</returns>
    private function getThresholdStatus(): string
    {
        if (!$this->relationLoaded('metricType.alertThresholds')) {
            return 'unknown';
        }

        // Find applicable threshold (host-specific or global)
        $threshold = $this->metricType->alertThresholds
            ->where('host_id', $this->host_id)
            ->where('is_active', true)
            ->first() ?: 
            $this->metricType->alertThresholds
                ->whereNull('host_id')
                ->where('is_active', true)
                ->first();

        if (!$threshold) {
            return 'no_threshold';
        }

        if ($this->value >= $threshold->critical_threshold) {
            return 'critical';
        } elseif ($this->value >= $threshold->warning_threshold) {
            return 'warning';
        }

        return 'normal';
    }

    /// <summary>
    /// Get age of metric in hours
    /// </summary>
    /// <returns>float</returns>
    private function getAgeHours(): float
    {
        return round($this->timestamp->diffInHours(now()), 2);
    }

    /// <summary>
    /// Get trend indicator based on recent metrics
    /// </summary>
    /// <returns>string</returns>
    private function getTrendIndicator(): string
    {
        if (!$this->relationLoaded('host.metrics')) {
            return 'unknown';
        }

        $recentMetrics = $this->host->metrics
            ->where('metric_type_id', $this->metric_type_id)
            ->where('timestamp', '>=', now()->subHours(2))
            ->sortBy('timestamp')
            ->take(5);

        if ($recentMetrics->count() < 3) {
            return 'insufficient_data';
        }

        $values = $recentMetrics->pluck('value')->toArray();
        $trend = end($values) - reset($values);
        $threshold = max($values) * 0.05; // 5% threshold

        if (abs($trend) < $threshold) {
            return 'stable';
        }

        return $trend > 0 ? 'increasing' : 'decreasing';
    }

    /// <summary>
    /// Assess data quality
    /// </summary>
    /// <returns>string</returns>
    private function getDataQuality(): string
    {
        $ageHours = $this->getAgeHours();

        if ($ageHours < 0.1) { // Less than 6 minutes
            return 'excellent';
        } elseif ($ageHours < 1) { // Less than 1 hour
            return 'good';
        } elseif ($ageHours < 4) { // Less than 4 hours
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /// <summary>
    /// Check if metric is recent (within last hour)
    /// </summary>
    /// <returns>bool</returns>
    private function isRecent(): bool
    {
        return $this->timestamp >= now()->subHour();
    }

    /// <summary>
    /// Get recent average for same metric type on same host
    /// </summary>
    /// <returns>float|null</returns>
    private function getRecentAverage(): ?float
    {
        $recentMetrics = $this->host->metrics
            ->where('metric_type_id', $this->metric_type_id)
            ->where('timestamp', '>=', now()->subHours(6));

        return $recentMetrics->isEmpty() ? null : 
               round($recentMetrics->avg('value'), 2);
    }

    /// <summary>
    /// Get daily minimum for same metric type on same host
    /// </summary>
    /// <returns>float|null</returns>
    private function getDailyMin(): ?float
    {
        $dailyMetrics = $this->host->metrics
            ->where('metric_type_id', $this->metric_type_id)
            ->where('timestamp', '>=', now()->subDay());

        return $dailyMetrics->isEmpty() ? null : 
               (float) $dailyMetrics->min('value');
    }

    /// <summary>
    /// Get daily maximum for same metric type on same host
    /// </summary>
    /// <returns>float|null</returns>
    private function getDailyMax(): ?float
    {
        $dailyMetrics = $this->host->metrics
            ->where('metric_type_id', $this->metric_type_id)
            ->where('timestamp', '>=', now()->subDay());

        return $dailyMetrics->isEmpty() ? null : 
               (float) $dailyMetrics->max('value');
    }

    /// <summary>
    /// Get variance from recent average
    /// </summary>
    /// <returns>float|null</returns>
    private function getVarianceFromAverage(): ?float
    {
        $recentAverage = $this->getRecentAverage();
        
        if ($recentAverage === null) {
            return null;
        }

        return round((($this->value - $recentAverage) / $recentAverage) * 100, 2);
    }

    #endregion
}