<?php

namespace App\Http\Controllers;

use App\Models\DirectoryMetric;
use App\Models\MonitoredDirectory;
use App\Http\Resources\DirectoryMetricResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Directory Metrics",
 *     description="API Endpoints for managing directory disk usage metrics"
 * )
 */
class DirectoryMetricController extends Controller
{
    #region Properties
    /// <summary>
    /// Default pagination size
    /// </summary>
    protected int $perPage = 50;
    #endregion

    #region Methods

    /**
     * @OA\Get(
     *      path="/api/directory-metrics",
     *      operationId="getDirectoryMetricsList",
     *      tags={"Directory Metrics"},
     *      summary="Get list of directory metrics",
     *      description="Returns paginated list of directory metrics with filtering options",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="directory_id",
     *          description="Filter by directory ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="from_date",
     *          description="Start date for filtering (Y-m-d H:i:s)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="to_date",
     *          description="End date for filtering (Y-m-d H:i:s)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="hours",
     *          description="Get metrics from last N hours",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", minimum=1, maximum=8760)
     *      ),
     *      @OA\Parameter(
     *          name="usage_threshold",
     *          description="Filter by usage percentage threshold",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="number", minimum=0, maximum=100)
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Number of items per page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", minimum=1, maximum=1000)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="List of directory metrics",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DirectoryMetric")),
     *              @OA\Property(property="meta", type="object",
     *                  @OA\Property(property="current_page", type="integer"),
     *                  @OA\Property(property="total", type="integer"),
     *                  @OA\Property(property="per_page", type="integer"),
     *                  @OA\Property(property="last_page", type="integer")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthorized")
     * )
     */
    /// <summary>
    /// Display a listing of directory metrics with filtering
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>AnonymousResourceCollection</returns>
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DirectoryMetric::with(['monitoredDirectory.host']);

        // Filter by directory
        if ($request->filled('directory_id')) {
            $query->where('directory_id', $request->directory_id);
        }

        // Date range filtering
        if ($request->filled('from_date')) {
            $query->where('timestamp', '>=', Carbon::parse($request->from_date));
        }

        if ($request->filled('to_date')) {
            $query->where('timestamp', '<=', Carbon::parse($request->to_date));
        }

        // Last N hours
        if ($request->filled('hours')) {
            $hours = min($request->hours, 8760); // Max 1 year
            $query->lastHours($hours);
        }

        // Usage threshold filtering
        if ($request->filled('usage_threshold')) {
            $threshold = $request->usage_threshold;
            $query->whereRaw('(used_space / total_space) * 100 >= ?', [$threshold]);
        }

        $perPage = min($request->get('per_page', $this->perPage), 1000);
        
        $metrics = $query->latest('timestamp')
                        ->paginate($perPage);

        return DirectoryMetricResource::collection($metrics)
            ->additional([
                'meta' => [
                    'total_metrics_24h' => DirectoryMetric::lastHours(24)->count(),
                    'avg_usage_percentage' => $this->getAverageUsagePercentage($query),
                    'unique_directories' => DirectoryMetric::distinct('directory_id')->count()
                ]
            ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * @OA\Post(
     *      path="/api/directory-metrics",
     *      operationId="storeDirectoryMetric",
     *      tags={"Directory Metrics"},
     *      summary="Store new directory metric",
     *      description="Creates a new directory metric entry",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"directory_id","used_space","total_space","available_space","file_count"},
     *              @OA\Property(property="directory_id", type="integer", example=1),
     *              @OA\Property(property="used_space", type="integer", example=1073741824, description="Used space in bytes"),
     *              @OA\Property(property="total_space", type="integer", example=10737418240, description="Total space in bytes"),
     *              @OA\Property(property="available_space", type="integer", example=9663676416, description="Available space in bytes"),
     *              @OA\Property(property="file_count", type="integer", example=1542),
     *              @OA\Property(property="timestamp", type="string", format="date-time", description="Optional - defaults to now")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Directory metric stored successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/DirectoryMetric"),
     *              @OA\Property(property="message", type="string", example="Directory metric stored successfully")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="Directory not found")
     * )
     */
    /// <summary>
    /// Store a newly created directory metric
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'directory_id' => 'required|integer|exists:monitored_directories,directory_id',
                'used_space' => 'required|integer|min:0',
                'total_space' => 'required|integer|min:1',
                'available_space' => 'required|integer|min:0',
                'file_count' => 'required|integer|min:0',
                'timestamp' => 'sometimes|date'
            ]);

            // Validation: used_space + available_space should not exceed total_space significantly
            $usedPlusAvailable = $validated['used_space'] + $validated['available_space'];
            if ($usedPlusAvailable > $validated['total_space'] * 1.1) { // Allow 10% tolerance
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'space_calculation' => ['Used space + Available space exceeds total space']
                    ]
                ], 422);
            }

            $validated['timestamp'] = $validated['timestamp'] ?? now();

            $metric = DirectoryMetric::create($validated);
            $metric->load(['monitoredDirectory.host']);

            return response()->json([
                'data' => new DirectoryMetricResource($metric),
                'message' => 'Directory metric stored successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/directory-metrics/{id}",
     *      operationId="getDirectoryMetric",
     *      tags={"Directory Metrics"},
     *      summary="Get directory metric details",
     *      description="Returns specific directory metric with related data",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Directory Metric ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Directory metric details",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/DirectoryMetric")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Metric not found")
     * )
     */
    /// <summary>
    /// Display the specified directory metric
    /// </summary>
    /// <param>DirectoryMetric $directoryMetric</param>
    /// <returns>JsonResponse</returns>
    public function show(DirectoryMetric $directoryMetric): JsonResponse
    {
        $directoryMetric->load(['monitoredDirectory.host']);

        return response()->json([
            'data' => new DirectoryMetricResource($directoryMetric)
        ]);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DirectoryMetric $directoryMetric)
    {
        //
    }

    /**
     * @OA\Put(
     *      path="/api/directory-metrics/{id}",
     *      operationId="updateDirectoryMetric",
     *      tags={"Directory Metrics"},
     *      summary="Update directory metric",
     *      description="Updates directory metric (rarely used)",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Directory Metric ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="used_space", type="integer", example=1073741824),
     *              @OA\Property(property="total_space", type="integer", example=10737418240),
     *              @OA\Property(property="available_space", type="integer", example=9663676416),
     *              @OA\Property(property="file_count", type="integer", example=1542),
     *              @OA\Property(property="timestamp", type="string", format="date-time")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Metric updated successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/DirectoryMetric"),
     *              @OA\Property(property="message", type="string", example="Directory metric updated successfully")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="Metric not found")
     * )
     */
    /// <summary>
    /// Update the specified directory metric
    /// </summary>
    /// <param>Request $request</param>
    /// <param>DirectoryMetric $directoryMetric</param>
    /// <returns>JsonResponse</returns>
    public function update(Request $request, DirectoryMetric $directoryMetric): JsonResponse
    {
        try {
            $validated = $request->validate([
                'used_space' => 'sometimes|integer|min:0',
                'total_space' => 'sometimes|integer|min:1',
                'available_space' => 'sometimes|integer|min:0',
                'file_count' => 'sometimes|integer|min:0',
                'timestamp' => 'sometimes|date'
            ]);

            // Validate space calculations if any space fields are being updated
            $usedSpace = $validated['used_space'] ?? $directoryMetric->used_space;
            $totalSpace = $validated['total_space'] ?? $directoryMetric->total_space;
            $availableSpace = $validated['available_space'] ?? $directoryMetric->available_space;

            $usedPlusAvailable = $usedSpace + $availableSpace;
            if ($usedPlusAvailable > $totalSpace * 1.1) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'space_calculation' => ['Used space + Available space exceeds total space']
                    ]
                ], 422);
            }

            $directoryMetric->update($validated);
            $directoryMetric->load(['monitoredDirectory.host']);

            return response()->json([
                'data' => new DirectoryMetricResource($directoryMetric),
                'message' => 'Directory metric updated successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *      path="/api/directory-metrics/{id}",
     *      operationId="deleteDirectoryMetric",
     *      tags={"Directory Metrics"},
     *      summary="Delete directory metric",
     *      description="Deletes specific directory metric",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Directory Metric ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=204,
     *          description="Metric deleted successfully"
     *      ),
     *      @OA\Response(response=404, description="Metric not found")
     * )
     */
    /// <summary>
    /// Remove the specified directory metric
    /// </summary>
    /// <param>DirectoryMetric $directoryMetric</param>
    /// <returns>JsonResponse</returns>
    public function destroy(DirectoryMetric $directoryMetric): JsonResponse
    {
        $directoryMetric->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *      path="/api/directory-metrics/directory/{directory}",
     *      operationId="getDirectoryMetricsByDirectory",
     *      tags={"Directory Metrics"},
     *      summary="Get metrics for specific directory",
     *      description="Returns metrics for a specific monitored directory",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="directory",
     *          description="Directory ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="limit",
     *          description="Limit number of results",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", minimum=1, maximum=1000)
     *      ),
     *      @OA\Parameter(
     *          name="hours",
     *          description="Get metrics from last N hours",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", minimum=1, maximum=8760)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Metrics for the directory",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DirectoryMetric")),
     *              @OA\Property(property="directory_info", type="object",
     *                  @OA\Property(property="directory_id", type="integer"),
     *                  @OA\Property(property="directory_path", type="string"),
     *                  @OA\Property(property="host_name", type="string")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=404, description="Directory not found")
     * )
     */
    /// <summary>
    /// Get all metrics for a specific directory
    /// </summary>
    /// <param>Request $request</param>
    /// <param>MonitoredDirectory $directory</param>
    /// <returns>JsonResponse</returns>
    public function getByDirectory(Request $request, MonitoredDirectory $directory): JsonResponse
    {
        $query = $directory->directoryMetrics()->with(['monitoredDirectory.host']);

        if ($request->filled('hours')) {
            $hours = min($request->hours, 8760);
            $query->lastHours($hours);
        }

        $limit = min($request->get('limit', 100), 1000);
        $metrics = $query->latest('timestamp')->limit($limit)->get();

        return response()->json([
            'data' => DirectoryMetricResource::collection($metrics),
            'directory_info' => [
                'directory_id' => $directory->directory_id,
                'directory_path' => $directory->directory_path,
                'host_name' => $directory->host->host_name ?? 'Unknown',
                'host_id' => $directory->host_id,
                'is_active' => $directory->is_active
            ],
            'meta' => [
                'total_metrics' => $metrics->count(),
                'oldest_metric' => $metrics->last()?->timestamp,
                'newest_metric' => $metrics->first()?->timestamp,
                'avg_usage_percentage' => $this->calculateAverageUsage($metrics)
            ]
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/directory-metrics/batch",
     *      operationId="batchStoreDirectoryMetrics",
     *      tags={"Directory Metrics"},
     *      summary="Batch store directory metrics",
     *      description="Store multiple directory metrics at once",
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
     *                      @OA\Property(property="directory_id", type="integer", example=1),
     *                      @OA\Property(property="used_space", type="integer", example=1073741824),
     *                      @OA\Property(property="total_space", type="integer", example=10737418240),
     *                      @OA\Property(property="available_space", type="integer", example=9663676416),
     *                      @OA\Property(property="file_count", type="integer", example=1542),
     *                      @OA\Property(property="timestamp", type="string", format="date-time")
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Metrics stored successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="stored", type="array", @OA\Items(ref="#/components/schemas/DirectoryMetric")),
     *              @OA\Property(property="errors", type="array", @OA\Items(type="object")),
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    /// <summary>
    /// Batch store directory metrics
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function batchStore(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'metrics' => 'required|array|min:1|max:1000',
                'metrics.*.directory_id' => 'required|integer|exists:monitored_directories,directory_id',
                'metrics.*.used_space' => 'required|integer|min:0',
                'metrics.*.total_space' => 'required|integer|min:1',
                'metrics.*.available_space' => 'required|integer|min:0',
                'metrics.*.file_count' => 'required|integer|min:0',
                'metrics.*.timestamp' => 'sometimes|date'
            ]);

            $stored = [];
            $errors = [];

            foreach ($validated['metrics'] as $index => $metricData) {
                try {
                    // Validate space calculations
                    $usedPlusAvailable = $metricData['used_space'] + $metricData['available_space'];
                    if ($usedPlusAvailable > $metricData['total_space'] * 1.1) {
                        $errors[] = [
                            'index' => $index,
                            'error' => 'Used space + Available space exceeds total space',
                            'data' => $metricData
                        ];
                        continue;
                    }

                    $metricData['timestamp'] = $metricData['timestamp'] ?? now();
                    $metric = DirectoryMetric::create($metricData);
                    $metric->load(['monitoredDirectory.host']);
                    $stored[] = $metric;

                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'data' => $metricData
                    ];
                }
            }

            return response()->json([
                'stored' => DirectoryMetricResource::collection(collect($stored)),
                'errors' => $errors,
                'message' => 'Batch operation completed. Stored: ' . count($stored) . ', Errors: ' . count($errors)
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * @OA\Post(
     *      path="/api/agent/directory-metrics",
     *      operationId="receiveDirectoryMetricsFromAgent",
     *      tags={"Directory Metrics"},
     *      summary="Receive metrics from agent",
     *      description="Endpoint for agents to submit directory metrics",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"host_identifier","metrics"},
     *              @OA\Property(property="host_identifier", type="string", example="192.168.1.100"),
     *              @OA\Property(
     *                  property="metrics",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="directory_path", type="string", example="/var/log"),
     *                      @OA\Property(property="used_space", type="integer", example=1073741824),
     *                      @OA\Property(property="total_space", type="integer", example=10737418240),
     *                      @OA\Property(property="available_space", type="integer", example=9663676416),
     *                      @OA\Property(property="file_count", type="integer", example=1542)
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Metrics received successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="received", type="integer"),
     *              @OA\Property(property="errors", type="array", @OA\Items(type="string")),
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Receive directory metrics from agent
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function receiveFromAgent(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'host_identifier' => 'required|string',
                'metrics' => 'required|array|min:1|max:100',
                'metrics.*.directory_path' => 'required|string',
                'metrics.*.used_space' => 'required|integer|min:0',
                'metrics.*.total_space' => 'required|integer|min:1',
                'metrics.*.available_space' => 'required|integer|min:0',
                'metrics.*.file_count' => 'required|integer|min:0'
            ]);

            // Find host by IP address or hostname
            $host = \App\Models\Host::where('ip_address', $validated['host_identifier'])
                                   ->orWhere('host_name', $validated['host_identifier'])
                                   ->first();

            if (!$host) {
                return response()->json([
                    'message' => 'Host not found',
                    'host_identifier' => $validated['host_identifier']
                ], 404);
            }

            $received = 0;
            $errors = [];
            $timestamp = now();

            foreach ($validated['metrics'] as $index => $metricData) {
                try {
                    // Find or create monitored directory
                    $directory = MonitoredDirectory::firstOrCreate([
                        'host_id' => $host->host_id,
                        'directory_path' => $metricData['directory_path']
                    ], [
                        'is_active' => true
                    ]);

                    // Create metric
                    DirectoryMetric::create([
                        'directory_id' => $directory->directory_id,
                        'used_space' => $metricData['used_space'],
                        'total_space' => $metricData['total_space'],
                        'available_space' => $metricData['available_space'],
                        'file_count' => $metricData['file_count'],
                        'timestamp' => $timestamp
                    ]);

                    $received++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'directory_path' => $metricData['directory_path'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'received' => $received,
                'errors' => $errors,
                'message' => "Received {$received} directory metrics from agent"
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get average usage percentage for a query
    /// </summary>
    /// <param>$query</param>
    /// <returns>float|null</returns>
    private function getAverageUsagePercentage($query): ?float
    {
        $metrics = $query->get(['used_space', 'total_space']);
        
        if ($metrics->isEmpty()) {
            return null;
        }

        $totalUsagePercentage = 0;
        $validMetrics = 0;

        foreach ($metrics as $metric) {
            if ($metric->total_space > 0) {
                $totalUsagePercentage += ($metric->used_space / $metric->total_space) * 100;
                $validMetrics++;
            }
        }

        return $validMetrics > 0 ? round($totalUsagePercentage / $validMetrics, 2) : null;
    }

    /// <summary>
    /// Calculate average usage percentage for a collection of metrics
    /// </summary>
    /// <param>$metrics</param>
    /// <returns>float|null</returns>
    private function calculateAverageUsage($metrics): ?float
    {
        if ($metrics->isEmpty()) {
            return null;
        }

        $totalUsagePercentage = 0;
        $validMetrics = 0;

        foreach ($metrics as $metric) {
            if ($metric->total_space > 0) {
                $totalUsagePercentage += ($metric->used_space / $metric->total_space) * 100;
                $validMetrics++;
            }
        }

        return $validMetrics > 0 ? round($totalUsagePercentage / $validMetrics, 2) : null;
    }

    #endregion
}