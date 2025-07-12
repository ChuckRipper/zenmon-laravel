<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/// <summary>
/// Collection resource for metrics with statistical analysis
/// </summary>
class MetricCollection extends ResourceCollection
{
    #region Properties

    /// <summary>
    /// Wrap the collection data
    /// </summary>
    public static $wrap = 'metrics';

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
                'total_metrics' => $this->collection->count(),
                'unique_hosts' => $this->collection->pluck('host_id')->unique()->count(),
                'unique_metric_types' => $this->collection->pluck('metric_type_id')->unique()->count(),
                'time_range' => [
                    'earliest' => $this->collection->min('timestamp'),
                    'latest' => $this->collection->max('timestamp'),
                    'span_hours' => $this->calculateTimeSpanHours()
                ],
                'statistics' => [
                    'average_value' => $this->collection->avg('value'),
                    'min_value' => $this->collection->min('value'),
                    'max_value' => $this->collection->max('value'),
                    'median_value' => $this->calculateMedian()
                ]
            ],
            'filters_applied' => [
                'host_id' => $request->get('host_id'),
                'metric_type_id' => $request->get('metric_type_id'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'min_value' => $request->get('min_value'),
                'max_value' => $request->get('max_value'),
            ]
        ];
    }

    /// <summary>
    /// Calculate time span in hours between earliest and latest metrics
    /// </summary>
    /// <returns>float</returns>
    private function calculateTimeSpanHours(): float
    {
        if ($this->collection->isEmpty()) {
            return 0.0;
        }

        $earliest = $this->collection->min('timestamp');
        $latest = $this->collection->max('timestamp');

        if (!$earliest || !$latest) {
            return 0.0;
        }

        return \Carbon\Carbon::parse($earliest)
            ->diffInHours(\Carbon\Carbon::parse($latest));
    }

    /// <summary>
    /// Calculate median value from metrics
    /// </summary>
    /// <returns>float</returns>
    private function calculateMedian(): float
    {
        $values = $this->collection->pluck('value')
            ->filter()
            ->sort()
            ->values()
            ->toArray();

        if (empty($values)) {
            return 0.0;
        }

        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            return $values[$middle];
        }
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
                'data_quality' => [
                    'completeness' => $this->calculateCompleteness(),
                    'freshness_hours' => $this->calculateFreshnessHours()
                ]
            ]
        ];
    }

    /// <summary>
    /// Calculate data completeness percentage
    /// </summary>
    /// <returns>float</returns>
    private function calculateCompleteness(): float
    {
        if ($this->collection->isEmpty()) {
            return 0.0;
        }

        $metricsWithValues = $this->collection->filter(function ($metric) {
            return !is_null($metric->value) && $metric->value !== '';
        })->count();

        return round(($metricsWithValues / $this->collection->count()) * 100, 2);
    }

    /// <summary>
    /// Calculate average freshness of metrics in hours
    /// </summary>
    /// <returns>float</returns>
    private function calculateFreshnessHours(): float
    {
        if ($this->collection->isEmpty()) {
            return 0.0;
        }

        $totalAgeHours = $this->collection->sum(function ($metric) {
            return \Carbon\Carbon::parse($metric->timestamp)->diffInHours(now());
        });

        return round($totalAgeHours / $this->collection->count(), 2);
    }

    #endregion
}