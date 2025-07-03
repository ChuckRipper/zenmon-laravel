<?php

namespace App\Http\Controllers;

use App\Models\Metric;
use App\Models\Host;
use App\Models\MetricType;
use App\Http\Resources\MetricResource;
use App\Http\Resources\MetricCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Metrics",
 *     description="API Endpoints for managing metrics data (UC30, UC31, UC32, UC33)"
 * )
 */
class MetricController extends Controller
{
    #region Methods

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

    /**
     * @OA\Get(
     *      path="/api/metrics",
     *      operationId="getMetricsList",
     *      tags={"Metrics"},
     *      summary="Get list of metrics with filtering (UC32, UC33)",
     *      description="Returns paginated list of metrics with filtering options for dashboard and historical data",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host_id",
     *          description="Filter by host ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="metric_type_id",
     *          description="Filter by metric type ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="from_date",
     *          description="Start date for filtering (YYYY-MM-DD HH:MM:SS)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="to_date",
     *          description="End date for filtering (YYYY-MM-DD HH:MM:SS)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Number of metrics per page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", default=50)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Metric")),
     *              @OA\Property(property="current_page", type="integer"),
     *              @OA\Property(property="per_page", type="integer"),
     *              @OA\Property(property="total", type="integer")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    /// <summary>
    /// Display a listing of metrics with filtering and pagination
    /// </summary>
    /// <param name="request">HTTP request with optional filters</param>
    /// <returns>JsonResponse</returns>
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

    /**
     * @OA\Post(
     *      path="/api/metrics",
     *      operationId="storeMetric",
     *      tags={"Metrics"},
     *      summary="Store single metric (UC30)",
     *      description="Store new metric data from agent",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"host_id", "metric_type_id", "value"},
     *              @OA\Property(property="host_id", type="integer", example=1),
     *              @OA\Property(property="metric_type_id", type="integer", example=1),
     *              @OA\Property(property="value", type="number", format="float", example=85.5),
     *              @OA\Property(property="timestamp", type="string", format="date-time", example="2025-06-22T10:30:00Z"),
     *              @OA\Property(property="additional_info", type="object", example={"process_count": 45})
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Metric stored successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/Metric")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Store a new metric (UC31)
    /// </summary>
    /// <param name="request">HTTP request with metric data</param>
    /// <returns>JsonResponse</returns>
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
            $timestamp = $request->timestamp ?
                Carbon::parse($request->timestamp)->format('Y-m-d H:i:s') :
                Carbon::now()->format('Y-m-d H:i:s');

            $metric = Metric::create([
                'host_id' => $request->host_id,
                'metric_type_id' => $request->metric_type_id,
                'value' => $request->value,
                'timestamp' => $timestamp,
                'additional_info' => $request->additional_info
            ]);

            // Update host last contact date
            $host = Host::find($request->host_id);
            if ($host) {
                $host->update(['last_contact_date' => Carbon::now()->format('Y-m-d H:i:s')]);
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

    /**
     * @OA\Post(
     *      path="/api/metrics/batch",
     *      operationId="storeBatchMetrics",
     *      tags={"Metrics"},
     *      summary="Store multiple metrics (UC31)",
     *      description="Store multiple metrics in batch for efficiency",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"metrics"},
     *              @OA\Property(
     *                  property="metrics",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="host_id", type="integer"),
     *                      @OA\Property(property="metric_type_id", type="integer"),
     *                      @OA\Property(property="value", type="number", format="float"),
     *                      @OA\Property(property="timestamp", type="string", format="date-time"),
     *                      @OA\Property(property="additional_info", type="object")
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Metrics stored successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="count", type="integer")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Store multiple metrics in batch (for agent efficiency)
    /// </summary>
    /// <param name="request">HTTP request with array of metrics</param>
    /// <returns>JsonResponse</returns>
    public function storeBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'metrics' => 'required|array|min:1|max:1000',
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
                $timestamp = isset($metricData['timestamp']) ?
                    Carbon::parse($metricData['timestamp'])->format('Y-m-d H:i:s') :
                    Carbon::now()->format('Y-m-d H:i:s');

                return [
                    'host_id' => $metricData['host_id'],
                    'metric_type_id' => $metricData['metric_type_id'],
                    'value' => $metricData['value'],
                    'timestamp' => $timestamp,
                    'additional_info' => isset($metricData['additional_info']) ?
                        json_encode($metricData['additional_info']) : null
                ];
            });

            $metrics->chunk(100)->each(function ($chunk) {
                Metric::insert($chunk->toArray());
            });

            $hostIds = collect($request->metrics)->pluck('host_id')->unique();
            Host::whereIn('host_id', $hostIds)->update([
                'last_contact_date' => Carbon::now()->format('Y-m-d H:i:s')
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

    /**
     * @OA\Get(
     *      path="/api/metrics/{metric}",
     *      operationId="showMetric",
     *      tags={"Metrics"},
     *      summary="Get specific metric",
     *      description="Returns detailed information about specific metric",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="metric",
     *          description="Metric ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Metric details",
     *          @OA\JsonContent(
     *              @OA\Property(property="data", ref="#/components/schemas/Metric")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Metric not found")
     * )
     */
    /// <summary>
    /// Display the specified metric
    /// </summary>
    /// <param name="id">Metric ID jako string</param>
    /// <returns>JsonResponse</returns>
    public function show(string $id): JsonResponse
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

    /**
     * @OA\Put(
     *      path="/api/metrics/{metric}",
     *      operationId="updateMetric",
     *      tags={"Metrics"},
     *      summary="Update metric",
     *      description="Update existing metric data",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="metric",
     *          description="Metric ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="value", type="number", format="float", example=90.0),
     *              @OA\Property(property="timestamp", type="string", format="date-time"),
     *              @OA\Property(property="additional_info", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Metric updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/Metric")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Update the specified metric
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <param name="id">Metric ID jako string</param>
    /// <returns>JsonResponse</returns>
    public function update(Request $request, string $id): JsonResponse
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

    /**
     * @OA\Delete(
     *      path="/api/metrics/{metric}",
     *      operationId="deleteMetric",
     *      tags={"Metrics"},
     *      summary="Delete metric",
     *      description="Remove metric from system",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="metric",
     *          description="Metric ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Metric deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Remove the specified metric
    /// </summary>
    /// <param name="id">Metric ID jako string</param>
    /// <returns>JsonResponse</returns>
    public function destroy(string $id): JsonResponse
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

    /**
     * @OA\Get(
     *      path="/api/metrics/latest/{hostId}",
     *      operationId="getLatestMetricsByHost",
     *      tags={"Metrics"},
     *      summary="Get latest metrics for host (UC32)",
     *      description="Get most recent metrics for specific host",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="hostId",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Latest metrics",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="host", type="string"),
     *              @OA\Property(property="metrics", type="array", @OA\Items(type="object"))
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get latest metrics for a specific host
    /// </summary>
    /// <param name="hostId">Host ID jako string</param>
    /// <returns>JsonResponse</returns>
    public function getLatestByHost(string $hostId): JsonResponse
    {
        $host = Host::find($hostId);

        if (!$host) {
            return response()->json([
                'message' => 'Host not found'
            ], 404);
        }

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

    /**
     * @OA\Get(
     *      path="/api/metrics/historical",
     *      operationId="getHistoricalMetrics",
     *      tags={"Metrics"},
     *      summary="Get historical metrics for trending (UC33)",
     *      description="Get historical metrics data with aggregation",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host_id",
     *          description="Host ID",
     *          required=true,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="metric_type_id",
     *          description="Metric type ID",
     *          required=true,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="hours",
     *          description="Hours of history",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", default=24)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Historical metrics data",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *              @OA\Property(property="meta", type="object")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get historical metrics for trending (UC33)
    /// </summary>
    /// <param name="request">HTTP request with filters</param>
    /// <returns>JsonResponse</returns>
    public function getHistorical(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'host_id' => 'required|integer|exists:hosts,host_id',
            'metric_type_id' => 'required|integer|exists:metric_types,metric_type_id',
            'hours' => 'nullable|integer|min:1|max:720'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $hours = $request->get('hours', 24);
        $dateFrom = Carbon::now()->subHours($hours);

        $metrics = Metric::selectRaw("
            DATE_FORMAT(timestamp, '%Y-%m-%d %H:00') as time_period,
            AVG(value) as avg_value,
            MIN(value) as min_value,
            MAX(value) as max_value,
            COUNT(*) as sample_count
        ")
        ->where('host_id', $request->host_id)
        ->where('metric_type_id', $request->metric_type_id)
        ->where('timestamp', '>=', $dateFrom)
        ->groupByRaw("DATE_FORMAT(timestamp, '%Y-%m-%d %H:00')")
        ->orderBy('time_period')
        ->get();

        return response()->json([
            'data' => $metrics,
            'meta' => [
                'hours' => $hours,
                'date_from' => $dateFrom->toISOString()
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *      path="/api/metrics/cleanup",
     *      operationId="cleanupOldMetrics",
     *      tags={"Metrics"},
     *      summary="Delete old metrics (maintenance)",
     *      description="Delete metrics older than specified days",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"days_to_keep"},
     *              @OA\Property(property="days_to_keep", type="integer", example=30, minimum=1, maximum=3650)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Cleanup completed",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="deleted_records", type="integer")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Delete old metrics (cleanup endpoint)
    /// </summary>
    /// <param name="request">HTTP request with cleanup parameters</param>
    /// <returns>JsonResponse</returns>
    public function cleanup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days_to_keep' => 'required|integer|min:1|max:3650'
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
            'deleted_records' => $deletedCount
        ]);
    }

    #endregion
}