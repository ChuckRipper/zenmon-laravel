<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetricTypeResource extends JsonResource
{
    #region Methods

    /// <summary>
    /// Transform the resource into an array.
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <returns>array<string, mixed> Transformed resource array</returns>
    public function toArray(Request $request): array
    {
        return [
            'metric_type_id' => $this->metric_type_id,
            'metric_name' => $this->metric_name,
            'unit' => $this->unit,
            'description' => $this->description,
            
            // Metrics count (when loaded)
            'metrics_count' => $this->whenCounted('metrics'),
            
            // Recent metrics count (when loaded)
            'recent_metrics_count' => $this->whenCounted('recentMetrics'),
            
            // Alert thresholds count (when loaded)
            'alert_thresholds_count' => $this->whenCounted('alertThresholds'),
            
            // Recent metrics (when loaded)
            'recent_metrics' => $this->whenLoaded('recentMetrics', function () {
                return $this->recentMetrics->map(function ($metric) {
                    return [
                        'metric_id' => $metric->metric_id,
                        'value' => $metric->value,
                        'timestamp' => $metric->timestamp,
                        'host' => [
                            'host_id' => $metric->host->host_id,
                            'host_name' => $metric->host->host_name,
                            'ip_address' => $metric->host->ip_address
                        ]
                    ];
                });
            }),
            
            // Alert thresholds (when loaded)
            'alert_thresholds' => $this->whenLoaded('alertThresholds', function () {
                return $this->alertThresholds->map(function ($threshold) {
                    return [
                        'threshold_id' => $threshold->threshold_id,
                        'warning_threshold' => $threshold->warning_threshold,
                        'critical_threshold' => $threshold->critical_threshold,
                        'is_active' => $threshold->is_active,
                        'host' => $threshold->host ? [
                            'host_id' => $threshold->host->host_id,
                            'host_name' => $threshold->host->host_name,
                            'ip_address' => $threshold->host->ip_address
                        ] : null, // null means global threshold
                        'created_by' => [
                            'user_id' => $threshold->createdByUser->id,
                            'login' => $threshold->createdByUser->login,
                            'first_name' => $threshold->createdByUser->first_name,
                            'last_name' => $threshold->createdByUser->last_name
                        ]
                    ];
                });
            }),
            
            // Latest metric per host (when loaded)
            'latest_metrics_by_host' => $this->whenLoaded('latestMetricsByHost', function () {
                return $this->latestMetricsByHost->map(function ($metric) {
                    return [
                        'host' => [
                            'host_id' => $metric->host->host_id,
                            'host_name' => $metric->host->host_name,
                            'ip_address' => $metric->host->ip_address
                        ],
                        'value' => $metric->value,
                        'timestamp' => $metric->timestamp,
                        'additional_info' => $metric->additional_info
                    ];
                });
            }),
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

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
                'timestamp' => now()->toISOString(),
                'metric_types_info' => [
                    'common_types' => [
                        'CPU' => 'Processor utilization percentage',
                        'RAM' => 'Memory utilization percentage', 
                        'Disk' => 'Disk space utilization percentage',
                        'Network' => 'Network response time in milliseconds'
                    ]
                ]
            ]
        ];
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
