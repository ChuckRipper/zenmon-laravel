<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/// <summary>
/// Collection resource for hosts with monitoring statistics
/// </summary>
class HostCollection extends ResourceCollection
{
    #region Properties

    /// <summary>
    /// Wrap the collection data
    /// </summary>
    public static $wrap = 'hosts';

    #endregion

    #region Methods

    /// <summary>
    /// Transform the resource collection into an array.
    /// </summary>
    /// <param name="Request">$request</param>
    /// <returns>array<string, mixed></returns>
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_hosts' => $this->collection->count(),
                'active_hosts' => $this->collection->where('is_active', true)->count(),
                'inactive_hosts' => $this->collection->where('is_active', false)->count(),
                'operating_systems' => $this->collection->pluck('operating_system')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray(),
                'average_uptime' => $this->calculateAverageUptime(),
            ],
            'filters_applied' => [
                'is_active' => $request->get('is_active'),
                'operating_system' => $request->get('operating_system'),
                'search' => $request->get('search'),
                'agent_version' => $request->get('agent_version'),
            ]
        ];
    }

    /// <summary>
    /// Calculate average uptime percentage for hosts
    /// </summary>
    /// <returns>float</returns>
    private function calculateAverageUptime(): float
    {
        $hostsWithConnection = $this->collection->filter(function ($host) {
            return $host->relationLoaded('connectionStatuses') && 
                   $host->connectionStatuses->isNotEmpty();
        });

        if ($hostsWithConnection->isEmpty()) {
            return 0.0;
        }

        $totalUptime = $hostsWithConnection->sum(function ($host) {
            $recentStatuses = $host->connectionStatuses
                ->where('last_check_date', '>=', now()->subDays(7));
            
            if ($recentStatuses->isEmpty()) {
                return 0;
            }

            $onlineCount = $recentStatuses->where('is_connected', true)->count();
            return ($onlineCount / $recentStatuses->count()) * 100;
        });

        return round($totalUptime / $hostsWithConnection->count(), 2);
    }

    /// <summary>
    /// Add additional metadata to the collection
    /// </summary>
    /// <param name="Request">$request</param>
    /// <returns>array<string, mixed></returns>
    public function with(Request $request): array
    {
        return [
            'summary' => [
                'generated_at' => now()->toISOString(),
                'monitoring_scope' => [
                    'total_monitored' => $this->collection->count(),
                    'health_check_timestamp' => now()->toISOString()
                ]
            ]
        ];
    }

    #endregion
}