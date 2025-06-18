<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'metric_id' => $this->metric_id,
            'value' => $this->value,
            'timestamp' => $this->timestamp,
            'additional_info' => $this->additional_info,
            
            // Host information (when loaded)
            'host' => $this->whenLoaded('host', function () {
                return [
                    'host_id' => $this->host->host_id,
                    'host_name' => $this->host->host_name,
                    'ip_address' => $this->host->ip_address
                ];
            }),
            
            // Metric type information (when loaded)
            'metric_type' => $this->whenLoaded('metricType', function () {
                return [
                    'metric_type_id' => $this->metricType->metric_type_id,
                    'metric_name' => $this->metricType->metric_name,
                    'unit' => $this->metricType->unit,
                    'description' => $this->metricType->description
                ];
            }),
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
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
    /// Customize the outgoing response for the resource.
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <param name="response">HTTP response</param>
    /// <returns>void</returns>
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'Metric');
    }

    #endregion
}
