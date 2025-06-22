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
     *              @OA\Property(property="metric_name", type="string", example="CPU"),
     *              @OA\Property(property="unit", type="string", example="%"),
     *              @OA\Property(property="description", type="string", example="Updated description")
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
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    /// <summary>
    /// Update the specified metric type
    /// </summary>
    /// <param name="request">HTTP request with updated data</param>
    /// <param name="id">Metric type ID</param>
    /// <returns>JsonResponse with updated metric type or error</returns>
    public function update(Request $request, int $id): JsonResponse
    {
        $metricType = MetricType::find($id);

        if (!$metricType) {
            return response()->json([
                'message' => 'Metric type not found'
            ], 404);
        }

        // Modify validation rules for unique check (exclude current record)
        $rules = $this->updateValidationRules;
        $rules['metric_name'] .= "|unique:metric_types,metric_name,{$id},metric_type_id";

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
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
     *      description="Delete metric type (only if not used by metrics)",
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
     *          description="Metric type deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=409, description="Cannot delete - metric type is in use")
     * )
     */
    /// <summary>
    /// Remove the specified metric type
    /// Only allow deletion if no metrics are using this type
    /// </summary>
    /// <param name="id">Metric type ID</param>
    /// <returns>JsonResponse with success message or error</returns>
    public function destroy(int $id): JsonResponse
    {
        $metricType = MetricType::find($id);

        if (!$metricType) {
            return response()->json([
                'message' => 'Metric type not found'
            ], 404);
        }

        // Check if metric type is being used by any metrics
        if ($metricType->metrics()->exists()) {
            return response()->json([
                'message' => 'Cannot delete metric type that is being used by existing metrics',
                'metrics_count' => $metricType->metrics()->count()
            ], 409); // Conflict
        }

        // Check if metric type is being used by any alert thresholds
        if ($metricType->alertThresholds()->exists()) {
            return response()->json([
                'message' => 'Cannot delete metric type that has alert thresholds configured',
                'thresholds_count' => $metricType->alertThresholds()->count()
            ], 409); // Conflict
        }

        try {
            $metricType->delete();

            return response()->json([
                'message' => 'Metric type deleted successfully'
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