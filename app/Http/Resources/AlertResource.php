<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'alert_id' => $this->alert_id,
            'host' => [
                'host_id' => $this->host->host_id,
                'host_name' => $this->host->host_name,
                'ip_address' => $this->host->ip_address
            ],
            'metric_type' => [
                'metric_type_id' => $this->metricType->metric_type_id,
                'metric_name' => $this->metricType->metric_name,
                'unit' => $this->metricType->unit
            ],
            'alert_level' => $this->alert_level,
            'alert_message' => $this->alert_message,
            'current_value' => (float) $this->current_value,
            'threshold_value' => (float) $this->threshold_value,
            'status' => $this->status,
            'created_at' => $this->created_at->toISOString(),
            'acknowledged_at' => $this->acknowledged_date?->toISOString(),
            'acknowledged_by' => $this->acknowledgedByUser?->full_name,
            'closed_at' => $this->closed_date?->toISOString(),
            'closed_by' => $this->closedByUser?->full_name,
            'close_comment' => $this->close_comment,
        ];
    }
}