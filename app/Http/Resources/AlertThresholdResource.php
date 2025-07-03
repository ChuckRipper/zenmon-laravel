<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="AlertThresholdResponse",
 *      type="object",
 *      title="AlertThresholdResponse",
 *      description="Alert Threshold API Response",
 *      @OA\Property(property="threshold_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", nullable=true, example=1, description="NULL for global thresholds"),
 *      @OA\Property(property="metric_type_id", type="integer", example=1),
 *      @OA\Property(property="warning_threshold", type="number", format="float", example=70.0),
 *      @OA\Property(property="critical_threshold", type="number", format="float", example=90.0),
 *      @OA\Property(property="is_active", type="boolean", example=true),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(
 *          property="host",
 *          type="object",
 *          nullable=true,
 *          @OA\Property(property="host_id", type="integer", example=1),
 *          @OA\Property(property="host_name", type="string", example="web-server-01"),
 *          @OA\Property(property="ip_address", type="string", example="192.168.1.100")
 *      ),
 *      @OA\Property(
 *          property="metric_type",
 *          type="object",
 *          @OA\Property(property="metric_type_id", type="integer", example=1),
 *          @OA\Property(property="metric_name", type="string", example="CPU"),
 *          @OA\Property(property="unit", type="string", example="%"),
 *          @OA\Property(property="description", type="string", example="CPU usage percentage")
 *      ),
 *      @OA\Property(
 *          property="created_by_user",
 *          type="object",
 *          nullable=true,
 *          @OA\Property(property="id", type="integer", example=1),
 *          @OA\Property(property="login", type="string", example="admin"),
 *          @OA\Property(property="full_name", type="string", example="System Administrator")
 *      )
 * )
 */
class AlertThresholdResource extends JsonResource
{
    #region Methods

    /// <summary>
    /// Transform the resource into an array.
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>array</returns>
    public function toArray(Request $request): array
    {
        return [
            'threshold_id' => $this->threshold_id,
            'host_id' => $this->host_id,
            'metric_type_id' => $this->metric_type_id,
            'warning_threshold' => (float) $this->warning_threshold,
            'critical_threshold' => (float) $this->critical_threshold,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Related data
            // 'host' => $this->when($this->relationLoaded('host') && $this->host, [
            //     'host_id' => $this->host->host_id,
            //     'host_name' => $this->host->host_name,
            //     'ip_address' => $this->host->ip_address,
            //     'operating_system' => $this->host->operating_system,
            //     'is_active' => $this->host->is_active
            // ]),

            'host' => $this->when($this->relationLoaded('host') && $this->host, function () {
                return [
                    'host_id' => $this->host->host_id,
                    'host_name' => $this->host->host_name,
                    'ip_address' => $this->host->ip_address,
                    'operating_system' => $this->host->operating_system,
                    'is_active' => $this->host->is_active
                ];
            }),

            'metric_type' => $this->when($this->relationLoaded('metricType'), [
                'metric_type_id' => $this->metricType?->metric_type_id,
                'metric_name' => $this->metricType?->metric_name,
                'unit' => $this->metricType?->unit,
                'description' => $this->metricType?->description
            ]),

            'created_by_user' => $this->when($this->relationLoaded('createdByUser'), [
                'id' => $this->createdByUser?->id,
                'login' => $this->createdByUser?->login,
                'full_name' => $this->createdByUser?->full_name,
                'role' => $this->createdByUser?->role
            ]),

            // Additional computed fields
            'threshold_summary' => [
                'scope' => $this->host_id ? 'host-specific' : 'global',
                'scope_name' => $this->host_id ? $this->host?->host_name : 'All Hosts',
                'threshold_range' => $this->getThresholdRangeText(),
                'severity_gap' => $this->getSeverityGap(),
                'status' => $this->is_active ? 'active' : 'inactive'
            ]
        ];
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get human readable threshold range text
    /// </summary>
    /// <returns>string</returns>
    private function getThresholdRangeText(): string
    {
        $unit = $this->metricType?->unit ?? '';
        return "Warning: {$this->warning_threshold}{$unit}, Critical: {$this->critical_threshold}{$unit}";
    }

    /// <summary>
    /// Get gap between warning and critical thresholds
    /// </summary>
    /// <returns>float</returns>
    private function getSeverityGap(): float
    {
        return round((float)$this->critical_threshold - (float)$this->warning_threshold, 2);
    }

    #endregion
}