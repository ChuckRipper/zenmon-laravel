<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="AlertResponse",
 *      type="object",
 *      title="AlertResponse",
 *      description="Alert API Resource with comprehensive alert information",
 *      @OA\Property(property="alert_id", type="integer", example=1),
 *      @OA\Property(property="host", type="object",
 *          @OA\Property(property="host_id", type="integer", example=1),
 *          @OA\Property(property="host_name", type="string", example="web-server-01"),
 *          @OA\Property(property="ip_address", type="string", example="192.168.1.100")
 *      ),
 *      @OA\Property(property="metric_type", type="object",
 *          @OA\Property(property="metric_type_id", type="integer", example=1),
 *          @OA\Property(property="metric_name", type="string", example="CPU"),
 *          @OA\Property(property="unit", type="string", example="%")
 *      ),
 *      @OA\Property(property="alert_level", type="string", enum={"Warning", "Critical"}, example="Critical"),
 *      @OA\Property(property="alert_message", type="string", example="CPU usage exceeded critical threshold"),
 *      @OA\Property(property="current_value", type="number", format="float", example=95.5),
 *      @OA\Property(property="threshold_value", type="number", format="float", example=90.0),
 *      @OA\Property(property="status", type="string", enum={"Active", "Acknowledged", "Closed"}, example="Active"),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="acknowledged_at", type="string", format="date-time", nullable=true),
 *      @OA\Property(property="acknowledged_by", type="string", nullable=true, example="John Doe"),
 *      @OA\Property(property="closed_at", type="string", format="date-time", nullable=true),
 *      @OA\Property(property="closed_by", type="string", nullable=true, example="Jane Smith"),
 *      @OA\Property(property="close_comment", type="string", nullable=true, example="Issue resolved after server restart"),
 *      @OA\Property(property="severity_indicator", type="string", enum={"low", "medium", "high", "critical"}),
 *      @OA\Property(property="time_active_hours", type="number", format="float", example=2.5),
 *      @OA\Property(property="escalation_required", type="boolean", example=false)
 * )
 */
class AlertResource extends JsonResource
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
            // Basic alert information
            'alert_id' => $this->alert_id,
            'alert_level' => $this->alert_level,
            'alert_message' => $this->alert_message,
            'current_value' => (float) $this->current_value,
            'threshold_value' => (float) $this->threshold_value,
            'status' => $this->status,

            // Related entities
            'host' => [
                'host_id' => $this->host?->host_id ?? null,
                'host_name' => $this->host?->host_name ?? 'Unknown',
                'ip_address' => $this->host?->ip_address ?? 'Unknown',
            ],
            'metric_type' => [
                'metric_type_id' => $this->metricType?->metric_type_id ?? null,
                'metric_name' => $this->metricType?->metric_name ?? 'Unknown',
                'unit' => $this->metricType?->unit ?? 'N/A'
            ],

            // Timestamps
            'created_at' => $this->created_at?->toISOString() ?? now()->toISOString(),
            'acknowledged_at' => $this->acknowledged_date?->toISOString(),
            'closed_at' => $this->closed_date?->toISOString(),

            // User information
            'acknowledged_by' => $this->acknowledgedByUser ? ($this->acknowledgedByUser->first_name . ' ' . $this->acknowledgedByUser->last_name) : null,
            'closed_by' => $this->closedByUser ? ($this->closedByUser->first_name . ' ' . $this->closedByUser->last_name) : null,
            'close_comment' => $this->close_comment,

            // Computed fields
            'severity_indicator' => $this->getSeverityIndicator(),
            'time_active_hours' => $this->getTimeActiveHours(),
            'escalation_required' => $this->isEscalationRequired(),

            // Additional context when relations are loaded
            'recent_metrics' => $this->when($this->relationLoaded('host.metrics'), function () {
                return $this->host->metrics()
                    ->where('metric_type_id', $this->metric_type_id)
                    ->where('timestamp', '>=', $this->created_at->subHours(2))
                    ->orderBy('timestamp', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($metric) {
                        return [
                            'value' => (float) $metric->value,
                            'timestamp' => $metric->timestamp->toISOString()
                        ];
                    });
            })
        ];
    }

    /// <summary>
    /// Get severity indicator based on level and duration
    /// </summary>
    /// <returns>string</returns>
    private function getSeverityIndicator(): string
    {
        $timeActiveHours = $this->getTimeActiveHours();
        
        if ($this->alert_level === 'Critical') {
            if ($timeActiveHours > 4) return 'critical';
            if ($timeActiveHours > 1) return 'high';
            return 'medium';
        }
        
        // Warning level
        if ($timeActiveHours > 8) return 'medium';
        return 'low';
    }

    /// <summary>
    /// Calculate how long the alert has been active in hours
    /// </summary>
    /// <returns>float</returns>
    private function getTimeActiveHours(): float
    {
        $endTime = $this->closed_date ?? now();
        // return round($this->created_at->diffInMinutes($endTime) / 60, 2);
        return $this->acknowledged_date
            ? now()->diffInMinutes($this->acknowledged_date) / 60
            : 0;
    }

    /// <summary>
    /// Determine if alert requires escalation
    /// </summary>
    /// <returns>bool</returns>
    private function isEscalationRequired(): bool
    {
        if ($this->status === 'Closed') {
            return false;
        }

        $timeActiveHours = $this->getTimeActiveHours();
        
        // Critical alerts need escalation after 2 hours if not acknowledged
        if ($this->alert_level === 'Critical' && 
            $this->status === 'Active' && 
            $timeActiveHours > 2) {
            return true;
        }

        // Warning alerts need escalation after 8 hours if not acknowledged
        if ($this->alert_level === 'Warning' && 
            $this->status === 'Active' && 
            $timeActiveHours > 8) {
            return true;
        }

        return false;
    }

    #endregion
}