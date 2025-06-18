<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HostResource extends JsonResource
{
    #region Methods
    
    /// <summary>
    /// Transform the resource into an array.
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>array<string, mixed></returns>
    public function toArray(Request $request): array
    {
        return [
            'host_id' => $this->host_id,
            'host_name' => $this->host_name,
            'ip_address' => $this->ip_address,
            'description' => $this->description,
            'operating_system' => $this->operating_system,
            'agent_version' => $this->agent_version,
            'is_active' => $this->is_active,
            'last_contact_date' => $this->last_contact_date?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relacje
            'configuration' => $this->when($this->relationLoaded('configuration'), function () {
                return [
                    'configuration_id' => $this->configuration->configuration_id,
                    'data_collection_interval' => $this->configuration->data_collection_interval,
                    'enable_cpu_monitoring' => $this->configuration->enable_cpu_monitoring,
                    'enable_ram_monitoring' => $this->configuration->enable_ram_monitoring,
                    'enable_disk_monitoring' => $this->configuration->enable_disk_monitoring,
                    'enable_network_monitoring' => $this->configuration->enable_network_monitoring,
                    'updated_by_user' => $this->configuration->updatedByUser?->full_name,
                    'last_updated' => $this->configuration->updated_at->toISOString()
                ];
            }),
            
            'latest_connection_status' => $this->when($this->relationLoaded('connectionStatuses'), function () {
                $latestStatus = $this->connectionStatuses->first();
                return $latestStatus ? [
                    'status' => $latestStatus->status,
                    'response_time' => $latestStatus->response_time,
                    'last_check_date' => $latestStatus->last_check_date->toISOString(),
                    'error_message' => $latestStatus->error_message
                ] : null;
            }),
            
            'active_alerts_count' => $this->when($this->relationLoaded('alerts'), function () {
                return $this->alerts->where('status', 'Active')->count();
            }),
            
            'monitored_directories_count' => $this->when($this->relationLoaded('monitoredDirectories'), function () {
                return $this->monitoredDirectories->where('is_active', true)->count();
            })
        ];
    }
    
    #endregion
}
