<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/// <summary>
/// Collection resource for alerts with pagination and metadata
/// </summary>
class AlertCollection extends ResourceCollection
{
    #region Properties

    /// <summary>
    /// Wrap the collection data
    /// </summary>
    public static $wrap = 'alerts';

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
                'total_alerts' => $this->collection->count(),
                'active_alerts' => $this->collection->where('status', 'Active')->count(),
                'acknowledged_alerts' => $this->collection->where('status', 'Acknowledged')->count(),
                'closed_alerts' => $this->collection->where('status', 'Closed')->count(),
                'warning_level' => $this->collection->where('alert_level', 'Warning')->count(),
                'critical_level' => $this->collection->where('alert_level', 'Critical')->count(),
            ],
            'filters_applied' => [
                'status' => $request->get('status'),
                'host_id' => $request->get('host_id'),
                'alert_level' => $request->get('alert_level'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
            ]
        ];
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
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'pagination_info' => [
                    'per_page' => $request->get('per_page', 15),
                    'current_page' => $request->get('page', 1)
                ]
            ]
        ];
    }

    #endregion
}