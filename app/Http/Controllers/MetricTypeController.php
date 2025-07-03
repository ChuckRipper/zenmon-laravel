<?php

namespace App\Http\Controllers;

use App\Http\Resources\MetricTypeResource;
use App\Models\MetricType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Metric Types",
 *     description="API Endpoints for managing metric types (CPU, RAM, Disk, Network)"
 * )
 */
class MetricTypeController extends Controller
{
    #region Properties
    
    /// <summary>
    /// Validation rules for metric type data
    /// </summary>
    private array $validationRules = [
        'metric_name' => 'required|string|max:50|unique:metric_types,metric_name',
        'unit' => 'required|string|max:10',
        'description' => 'nullable|string|max:200'
    ];

    /// <summary>
    /// Validation rules for updating metric type
    /// </summary>
    private array $updateValidationRules = [
        'metric_name' => 'required|string|max:50',
        'unit' => 'required|string|max:10',
        'description' => 'nullable|string|max:200'
    ];

    #endregion

    #region Methods

    /**
     * @OA\Get(
     *      path="/api/metric-types",
     *      operationId="getMetricTypesList",
     *      tags={"Metric Types"},
     *      summary="Get list of metric types",
     *      description="Returns list of available metric types with optional filtering",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="search",
     *          description="Search by metric name or description",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="unit",
     *          description="Filter by unit",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Number of items per page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", default=20)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MetricType"))
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Display a listing of metric types
    /// </summary>
    /// <param name="request">HTTP request with optional filters</param>
    /// <returns>JsonResponse with paginated metric types</returns>
    public function index(Request $request): JsonResponse
    {
        $query = MetricType::query();

        // Search by metric name
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('metric_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        // Filter by unit
        if ($request->has('unit')) {
            $query->where('unit', $request->get('unit'));
        }

        // Order by metric name
        $query->orderBy('metric_name');

        $metricTypes = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => MetricTypeResource::collection($metricTypes),
            'meta' => [
                'current_page' => $metricTypes->currentPage(),
                'per_page' => $metricTypes->perPage(),
                'total' => $metricTypes->total(),
                'last_page' => $metricTypes->lastPage()
            ]
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/metric-types",
     *      operationId="storeMetricType",
     *      tags={"Metric Types"},
     *      summary="Create new metric type",
     *      description="Create new metric type definition",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"metric_name", "unit"},
     *              @OA\Property(property="metric_name", type="string", example="CPU", description="Unique metric name"),
     *              @OA\Property(property="unit", type="string", example="%", description="Unit of measurement"),
     *              @OA\Property(property="description", type="string", example="CPU usage percentage")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Metric type created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/MetricType")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    /// <summary>
    /// Store a newly created metric type
    /// </summary>
    /// <param name="request">HTTP request with metric type data</param>
    /// <returns>JsonResponse with created metric type or error</returns>
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
            $metricType = MetricType::create([
                'metric_name' => $request->metric_name,
                'unit' => $request->unit,
                'description' => $request->description
            ]);

            return response()->json([
                'message' => 'Metric type created successfully',
                'data' => new MetricTypeResource($metricType)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating metric type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/metric-types/{metricType}",
     *      operationId="showMetricType",
     *      tags={"Metric Types"},
     *      summary="Get specific metric type",
     *      description="Returns detailed information about specific metric type",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="metricType",
     *          description="Metric Type ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Metric type details",
     *          @OA\JsonContent(
     *              @OA\Property(property="data", ref="#/components/schemas/MetricType")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Metric type not found")
     * )
     */
    /// <summary>
    /// Display the specified metric type
    /// </summary>
    /// <param name="id">Metric type ID</param>
    /// <returns>JsonResponse with metric type data or 404</returns>
    public function show(int $id): JsonResponse
    {
        $metricType = MetricType::find($id);

        if (!$metricType) {
            return response()->json([
                'message' => 'Metric type not found'
            ], 404);
        }

        return response()->json([
            'data' => new MetricTypeResource($metricType)
        ]);
    }

    /**
     * @OA\Put(
     *      path="/api/metric-types/{metricType}",
     *      operationId="updateMetricType",
     *      tags={"Metric Types"},
     *      summary="Update metric type",
     *      description="Update existing metric type definition",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="metricType",
     *          description="Metric Type ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"metric_name", "unit"},
     *              @OA\Property(property="metric_name", type="string", example="CPU", description="Unique metric name"),
     *              @OA\Property(property="unit", type="string", example="%", description="Unit of measurement"),
     *              @OA\Property(property="description", type="string", example="CPU usage percentage")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Metric type updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/MetricType")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="Metric type not found"),
     *      @OA\Response(response=409, description="Metric name already exists")
     * )
     */
    /// <summary>
    /// Update the specified metric type
    /// </summary>
    /// <param name="request">HTTP request with updated metric type data</param>
    /// <param name="int">Metric type ID</param>
    /// <returns>JsonResponse with updated metric type or error</returns>
    public function update(Request $request, int $id): JsonResponse
    {
        $metricType = MetricType::find($id);

        if (!$metricType) {
            return response()->json([
                'message' => 'Metric type not found'
            ], 404);
        }

        // Create validation rules for update (unique except current record)
        $updateRules = $this->updateValidationRules;
        $updateRules['metric_name'] = $updateRules['metric_name'] . ',metric_types,metric_name,' . $id . ',metric_type_id';

        $validator = Validator::make($request->all(), $updateRules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if changing name to an existing one
        if ($request->metric_name !== $metricType->metric_name) {
            $existing = MetricType::where('metric_name', $request->metric_name)
                                 ->where('metric_type_id', '!=', $id)
                                 ->first();
            
            if ($existing) {
                return response()->json([
                    'message' => 'Metric name already exists',
                    'error' => 'A metric type with this name already exists'
                ], 409);
            }
        }

        try {
            $metricType->update([
                'metric_name' => $request->metric_name,
                'unit' => $request->unit,
                'description' => $request->description
            ]);

            return response()->json([
                'message' => 'Metric type updated successfully',
                'data' => new MetricTypeResource($metricType)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating metric type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *      path="/api/metric-types/{metricType}",
     *      operationId="deleteMetricType",
     *      tags={"Metric Types"},
     *      summary="Delete metric type",
     *      description="Delete existing metric type (only if no metrics exist)",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="metricType",
     *          description="Metric Type ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=false,
     *          @OA\JsonContent(
     *              @OA\Property(property="force", type="boolean", example=false, description="Force delete even with existing metrics")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Metric type deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Metric type deleted successfully")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Metric type not found"),
     *      @OA\Response(response=409, description="Cannot delete - metrics exist"),
     *      @OA\Response(response=500, description="Server error")
     * )
     */
    /// <summary>
    /// Remove the specified metric type
    /// </summary>
    /// <param name="request">HTTP request with optional force parameter</param>
    /// <param name="int">Metric type ID</param>
    /// <returns>JsonResponse with success message or error</returns>
    public function destroy(Request $request, int $id): JsonResponse
    {
        $metricType = MetricType::find($id);

        if (!$metricType) {
            return response()->json([
                'message' => 'Metric type not found'
            ], 404);
        }

        // Check if there are existing metrics using this type
        $metricsCount = $metricType->metrics()->count();
        $alertsCount = $metricType->alerts()->count();
        $thresholdsCount = $metricType->alertThresholds()->count();

        $forceDelete = filter_var($request->get('force', false), FILTER_VALIDATE_BOOLEAN);

        if (($metricsCount > 0 || $alertsCount > 0 || $thresholdsCount > 0) && !$forceDelete) {
            return response()->json([
                'message' => 'Cannot delete metric type - dependent records exist',
                'details' => [
                    'metrics_count' => $metricsCount,
                    'alerts_count' => $alertsCount,
                    'thresholds_count' => $thresholdsCount,
                    'suggestion' => 'Use force=true parameter to delete anyway'
                ]
            ], 409);
        }

        try {
            // If force delete, first remove all dependent records
            if ($forceDelete) {
                // Delete metrics (this will also cascade to alerts)
                $metricType->metrics()->delete();
                
                // Delete alert thresholds
                $metricType->alertThresholds()->delete();
                
                // Delete remaining alerts
                $metricType->alerts()->delete();
            }

            $metricType->delete();

            return response()->json([
                'message' => 'Metric type deleted successfully',
                'deleted_dependencies' => $forceDelete ? [
                    'metrics' => $metricsCount,
                    'alerts' => $alertsCount,
                    'thresholds' => $thresholdsCount
                ] : []
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting metric type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/metric-types/stats",
     *      operationId="getMetricTypesWithStats",
     *      tags={"Metric Types"},
     *      summary="Get metric types with statistics",
     *      description="Returns metric types with usage statistics for dashboard",
     *      security={{"sanctum":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="Metric types with statistics",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(
     *                  allOf={
     *                      @OA\Schema(ref="#/components/schemas/MetricType"),
     *                      @OA\Schema(
     *                          type="object",
     *                          @OA\Property(property="metrics_count", type="integer"),
     *                          @OA\Property(property="active_hosts_count", type="integer"),
     *                          @OA\Property(property="last_metric_date", type="string", format="date-time")
     *                      )
     *                  }
     *              ))
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get metric types with recent metrics count
    /// Useful for dashboard statistics
    /// </summary>
    /// <returns>JsonResponse with metric types and statistics</returns>
    public function getWithStats(): JsonResponse
    {
        $metricTypes = MetricType::withCount([
            'metrics',
            'metrics as recent_metrics_count' => function ($query) {
                $query->where('timestamp', '>=', now()->subHours(24));
            },
            'alertThresholds'
        ])->orderBy('metric_name')->get();

        return response()->json([
            'data' => $metricTypes->map(function ($metricType) {
                return [
                    'metric_type_id' => $metricType->metric_type_id,
                    'metric_name' => $metricType->metric_name,
                    'unit' => $metricType->unit,
                    'description' => $metricType->description,
                    'total_metrics' => $metricType->metrics_count,
                    'recent_metrics_24h' => $metricType->recent_metrics_count,
                    'alert_thresholds_count' => $metricType->alert_thresholds_count,
                    'created_at' => $metricType->created_at,
                    'updated_at' => $metricType->updated_at
                ];
            })
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/metric-types/units",
     *      operationId="getAvailableUnits",
     *      tags={"Metric Types"},
     *      summary="Get available units",
     *      description="Returns list of commonly used units for creating metric types",
     *      security={{"sanctum":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="Available units",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="unit", type="string", example="%"),
     *                  @OA\Property(property="description", type="string", example="Percentage"),
     *                  @OA\Property(property="category", type="string", example="Usage")
     *              ))
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get available units for creating new metric types
    /// </summary>
    /// <returns>JsonResponse with common units and currently used units</returns>
    public function getAvailableUnits(): JsonResponse
    {
        $commonUnits = [
            '%' => 'Percentage',
            'ms' => 'Milliseconds',
            's' => 'Seconds',
            'MB' => 'Megabytes',
            'GB' => 'Gigabytes',
            'KB/s' => 'Kilobytes per second',
            'MB/s' => 'Megabytes per second',
            'count' => 'Count/Number',
            '°C' => 'Celsius',
            'RPM' => 'Revolutions per minute'
        ];

        return response()->json([
            'common_units' => $commonUnits,
            'used_units' => MetricType::distinct()->pluck('unit')->sort()->values()
        ]);
    }

    #endregion
}