<?php

namespace App\Http\Controllers;

use App\Http\Resources\MetricResource;
use App\Models\Metric;
use App\Models\Host;
use App\Models\MetricType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MetricController extends Controller
{
    #region Properties
    
    /// <summary>
    /// Validation rules for metric data
    /// </summary>
    private array $validationRules = [
        'host_id' => 'required|integer|exists:hosts,host_id',
        'metric_type_id' => 'required|integer|exists:metric_types,metric_type_id',
        'value' => 'required|numeric|min:0',
        'timestamp' => 'nullable|date',
        'additional_info' => 'nullable|array'
    ];

    #endregion

    #region Methods

    /// <summary>
    /// Display a listing of metrics with filtering and pagination
    /// Supports filtering by host_id, metric_type_id, date range
    /// </summary>
    /// <param name="request">HTTP request with optional filters</param>
    /// <returns>JsonResponse with paginated metrics</returns>
    public function index(Request $request): JsonResponse
    {
        $query = Metric::with(['host', 'metricType']);

        // Filter by host
        if ($request->has('host_id')) {
            $query->where('host_id', $request->host_id);
        }

        // Filter by metric type
        if ($request->has('metric_type_id')) {
            $query->where('metric_type_id', $request->metric_type_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('timestamp', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('timestamp', '<=', $request->date_to);
        }

        // Default: only recent metrics (last 24h) to avoid performance issues
        if (!$request->has('date_from') && !$request->has('date_to')) {
            $query->where('timestamp', '>=', Carbon::now()->subDay());
        }

        // Order by timestamp descending
        $query->orderBy('timestamp', 'desc');

        $metrics = $query->paginate($request->get('per_page', 100));

        return response()->json([
            'data' => MetricResource::collection($metrics),
            'meta' => [
                'current_page' => $metrics->currentPage(),
                'per_page' => $metrics->perPage(),
                'total' => $metrics->total(),
                'last_page' => $metrics->lastPage()
            ]
        ]);
    }

    /// <summary>
    /// Store a new metric (UC31: Agent sends data to web application)
    /// This is the main endpoint for agents to submit monitoring data
    /// </summary>
    /// <param name="request">HTTP request with metric data</param>
    /// <returns>JsonResponse with created metric or error</returns>
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->validationRules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $metric = Metric::create([
                'host_id' => $request->host_id,
                'metric_type_id' => $request->metric_type_id,
                'value' => $request->value,
                'timestamp' => $request->timestamp ?? Carbon::now(),
                'additional_info' => $request->additional_info
            ]);

            // Update host last contact date
            $host = Host::find($request->host_id);
            if ($host) {
                $host->update(['last_contact_date' => Carbon::now()]);
            }

            return response()->json([
                'message' => 'Metric stored successfully',
                'data' => new MetricResource($metric->load(['host', 'metricType']))
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error storing metric',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /// <summary>
    /// Store multiple metrics in batch (for agent efficiency)
    /// </summary>
    /// <param name="request">HTTP request with array of metrics</param>
    /// <returns>JsonResponse with batch operation result</returns>
    public function storeBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'metrics' => 'required|array|min:1|max:1000', // Limit batch size
            'metrics.*.host_id' => 'required|integer|exists:hosts,host_id',
            'metrics.*.metric_type_id' => 'required|integer|exists:metric_types,metric_type_id',
            'metrics.*.value' => 'required|numeric|min:0',
            'metrics.*.timestamp' => 'nullable|date',
            'metrics.*.additional_info' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $metrics = collect($request->metrics)->map(function ($metricData) {
                return [
                    'host_id' => $metricData['host_id'],
                    'metric_type_id' => $metricData['metric_type_id'],
                    'value' => $metricData['value'],
                    'timestamp' => $metricData['timestamp'] ?? Carbon::now(),
                    'additional_info' => isset($metricData['additional_info']) ? 
                        json_encode($metricData['additional_info']) : null,
                    // 'created_at' => Carbon::now(),
                    // 'updated_at' => Carbon::now()
                ];
            });

            // Insert in chunks for better performance
            $metrics->chunk(100)->each(function ($chunk) {
                Metric::insert($chunk->toArray());
            });

            // Update last contact for all affected hosts
            $hostIds = collect($request->metrics)->pluck('host_id')->unique();
            Host::whereIn('host_id', $hostIds)->update([
                'last_contact_date' => Carbon::now()
            ]);

            return response()->json([
                'message' => 'Metrics stored successfully',
                'count' => count($request->metrics)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error storing metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /// <summary>
    /// Display the specified metric
    /// </summary>
    /// <param name="id">Metric ID</param>
    /// <returns>JsonResponse with metric data or 404</returns>
    public function show(int $id): JsonResponse
    {
        $metric = Metric::with(['host', 'metricType'])->find($id);

        if (!$metric) {
            return response()->json([
                'message' => 'Metric not found'
            ], 404);
        }

        return response()->json([
            'data' => new MetricResource($metric)
        ]);
    }

    /// <summary>
    /// Update the specified metric
    /// </summary>
    /// <param name="request">HTTP request with updated data</param>
    /// <param name="id">Metric ID</param>
    /// <returns>JsonResponse with updated metric or error</returns>
    public function update(Request $request, int $id): JsonResponse
    {
        $metric = Metric::find($id);

        if (!$metric) {
            return response()->json([
                'message' => 'Metric not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'value' => 'required|numeric|min:0',
            'timestamp' => 'nullable|date',
            'additional_info' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $metric->update([
                'value' => $request->value,
                'timestamp' => $request->timestamp ?? $metric->timestamp,
                'additional_info' => $request->additional_info
            ]);

            return response()->json([
                'message' => 'Metric updated successfully',
                'data' => new MetricResource($metric->load(['host', 'metricType']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating metric',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /// <summary>
    /// Remove the specified metric
    /// </summary>
    /// <param name="id">Metric ID</param>
    /// <returns>JsonResponse with success message or error</returns>
    public function destroy(int $id): JsonResponse
    {
        $metric = Metric::find($id);

        if (!$metric) {
            return response()->json([
                'message' => 'Metric not found'
            ], 404);
        }

        try {
            $metric->delete();

            return response()->json([
                'message' => 'Metric deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting metric',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /// <summary>
    /// Get latest metrics for a specific host (UC32: View current metrics)
    /// </summary>
    /// <param name="hostId">Host ID</param>
    /// <returns>JsonResponse with latest metrics for each metric type</returns>
    public function getLatestByHost(int $hostId): JsonResponse
    {
        $host = Host::find($hostId);
        
        if (!$host) {
            return response()->json([
                'message' => 'Host not found'
            ], 404);
        }

        // Get latest metric for each metric type for this host
        $latestMetrics = MetricType::with(['metrics' => function ($query) use ($hostId) {
            $query->where('host_id', $hostId)
                  ->latest('timestamp')
                  ->limit(1);
        }])->get();

        $result = $latestMetrics->map(function ($metricType) {
            $latestMetric = $metricType->metrics->first();
            return [
                'metric_type' => $metricType->metric_name,
                'unit' => $metricType->unit,
                'value' => $latestMetric ? $latestMetric->value : null,
                'timestamp' => $latestMetric ? $latestMetric->timestamp : null,
                'additional_info' => $latestMetric ? $latestMetric->additional_info : null
            ];
        });

        return response()->json([
            'host' => $host->host_name,
            'metrics' => $result
        ]);
    }

    /// <summary>
    /// Get historical metrics for trending (UC33: View historical data)
    /// </summary>
    /// <param name="request">HTTP request with filters</param>
    /// <returns>JsonResponse with aggregated historical data</returns>
    public function getHistorical(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'host_id' => 'required|integer|exists:hosts,host_id',
            'metric_type_id' => 'required|integer|exists:metric_types,metric_type_id',
            'hours' => 'nullable|integer|min:1|max:720', // Max 30 days
            'interval' => 'nullable|in:minute,hour,day'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $hours = $request->get('hours', 24); // Default 24 hours
        $interval = $request->get('interval', 'hour');
        
        $dateFrom = Carbon::now()->subHours($hours);
        
        // Build aggregation query based on interval
        $groupBy = match($interval) {
            'minute' => "DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i')",
            'hour' => "DATE_FORMAT(timestamp, '%Y-%m-%d %H:00')",
            'day' => "DATE_FORMAT(timestamp, '%Y-%m-%d')",
            default => "DATE_FORMAT(timestamp, '%Y-%m-%d %H:00')"
        };

        $metrics = Metric::selectRaw("
            {$groupBy} as time_period,
            AVG(value) as avg_value,
            MIN(value) as min_value,
            MAX(value) as max_value,
            COUNT(*) as sample_count
        ")
        ->where('host_id', $request->host_id)
        ->where('metric_type_id', $request->metric_type_id)
        ->where('timestamp', '>=', $dateFrom)
        ->groupByRaw($groupBy)
        ->orderBy('time_period')
        ->get();

        return response()->json([
            'data' => $metrics,
            'meta' => [
                'interval' => $interval,
                'hours' => $hours,
                'date_from' => $dateFrom->toISOString()
            ]
        ]);
    }

    /// <summary>
    /// Delete old metrics (maintenance endpoint)
    /// </summary>
    /// <param name="request">HTTP request with cleanup parameters</param>
    /// <returns>JsonResponse with cleanup results</returns>
    public function cleanup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days_to_keep' => 'required|integer|min:1|max:3650' // Max 10 years
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $cutoffDate = Carbon::now()->subDays($request->days_to_keep);
        
        $deletedCount = Metric::where('timestamp', '<', $cutoffDate)->delete();

        return response()->json([
            'message' => 'Cleanup completed',
            'deleted_records' => $deletedCount,
            'cutoff_date' => $cutoffDate->toISOString()
        ]);
    }

    #endregion
}