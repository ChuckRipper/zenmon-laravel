<?php

namespace App\Http\Controllers;

use App\Http\Resources\MetricTypeResource;
use App\Models\MetricType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
    /// Display a listing of metric types (API + Web)
    /// </summary>
    /// <param name="request">HTTP request with optional filters</param>
    /// <returns>JsonResponse with paginated metric types</returns>
    public function index(Request $request): JsonResponse|View
    {
        try {
            $query = MetricType::query();

            // Apply search filter
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where('metric_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            }

            // Apply unit filter
            if ($request->filled('unit')) {
                $query->where('unit', $request->get('unit'));
            }

            $query->orderBy('metric_name');
            
            $perPage = $request->get('per_page', 20);
            $paginated = $query->paginate($perPage);

            // API Response
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => MetricTypeResource::collection($paginated),
                    'meta' => [
                        'current_page' => $paginated->currentPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                        'last_page' => $paginated->lastPage(),
                    ],
                ], 200);
            }

            // Web Response
            return view('admin.metric-types.index', [
                'types' => $paginated,
            ]);

        } catch (\Exception $e) {
            Log::error('MetricTypeController@index failed', [
                'error' => $e->getMessage(),
                'request_params' => $request->all(),
                'user_id' => auth()->id()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve metric types'
                ], 500);
            }

            return back()->with('error', 'Błąd podczas pobierania typów metryk.');
        }
    }

    /// <summary>
    /// Show create form for metric types (Web only)
    /// </summary>
    /// <param name="request">Request object</param>
    /// <returns>View</returns>
    public function create(Request $request): View
    {
        if ($request->wantsJson()) {
            abort(404, 'API does not support create forms');
        }
        
        return view('admin.metric-types.create');
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
    /// <returns>JsonResponse|RedirectResponse</returns>
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $validator = Validator::make($request->all(), $this->validationRules);

            if ($validator->fails()) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors(),
                    ], 422);
                }
                return back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $metricType = MetricType::create($request->only('metric_name', 'unit', 'description'));

            Log::info('Metric type created', [
                'metric_type_id' => $metricType->getKey(),
                'metric_name' => $metricType->metric_name,
                'created_by' => auth()->user()->login ?? 'system'
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Metric type created successfully',
                    'data' => new MetricTypeResource($metricType),
                ], 201);
            }

            return redirect()
                ->route('admin.metric-types.index')
                ->with('success', 'Typ metryki dodany.');

        } catch (\Exception $e) {
            Log::error('MetricTypeController@store failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->except(['password']),
                'created_by' => auth()->id()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create metric type'
                ], 500);
            }

            return back()->with('error', 'Błąd podczas tworzenia typu metryki.');
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
    /// Display the specified metric type (API only)
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

    /// <summary>
    /// Show edit form for metric type (Web only)
    /// </summary>
    /// <param name="request">Request object</param>
    /// <param name="metricType">MetricType model instance</param>
    /// <returns>JsonResponse|View</returns>
    public function edit(Request $request, MetricType $metricType): JsonResponse|View
    {
        if ($request->wantsJson()) {
            return response()->json([
                'data' => new MetricTypeResource($metricType),
            ]);
        }
        
        return view('admin.metric-types.edit', compact('metricType'));
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
    /// Update metric type (API + Web)
    /// </summary>
    /// <param name="request">Request with update data</param>
    /// <param name="metricType">MetricType model instance</param>
    /// <returns>JsonResponse|RedirectResponse</returns>
    public function update(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $metricType = MetricType::find($id);

        if (!$metricType) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Metric type not found'
                ], 404);
            }
            return back()->with('error', 'Typ metryki nie został znaleziony.');
        }

        // Create validation rules for update (unique except current record)
        $updateRules = $this->updateValidationRules;
        $updateRules['metric_name'] = $updateRules['metric_name'] . ',metric_types,metric_name,' . $id . ',metric_type_id';

        $validator = Validator::make($request->all(), $updateRules);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        // API: Check if changing name to an existing one (dodatkowa walidacja)
        if ($request->wantsJson() && $request->metric_name !== $metricType->metric_name) {
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

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Metric type updated successfully',
                    'data' => new MetricTypeResource($metricType)
                ]);
            }

            return redirect()
                ->route('admin.metric-types.index')
                ->with('success', 'Typ metryki zaktualizowany.');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Error updating metric type',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Błąd podczas aktualizacji typu metryki.');
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
    /// Remove the specified metric type (API + Web)
    /// </summary>
    /// <param name="request">HTTP request with optional force parameter</param>
    /// <param name="id">Metric type ID</param>
    /// <returns>JsonResponse|RedirectResponse</returns>
    public function destroy(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $metricType = MetricType::find($id);

        if (!$metricType) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Metric type not found'
                ], 404);
            }
            return back()->with('error', 'Typ metryki nie został znaleziony.');
        }

        // Check if there are existing metrics using this type
        $metricsCount = $metricType->metrics()->count();
        $alertsCount = $metricType->alerts()->count();
        $thresholdsCount = $metricType->alertThresholds()->count();

        // For API: support force delete parameter
        $forceDelete = $request->wantsJson() ? 
            filter_var($request->get('force', false), FILTER_VALIDATE_BOOLEAN) : false;

        if (($metricsCount > 0 || $alertsCount > 0 || $thresholdsCount > 0) && !$forceDelete) {
            if ($request->wantsJson()) {
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
            
            return back()->with('error', 
                "Nie można usunąć typu metryki. Jest używany przez {$metricsCount} metryk, {$alertsCount} alertów i {$thresholdsCount} progów."
            );
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

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Metric type deleted successfully',
                    'deleted_dependencies' => $forceDelete ? [
                        'metrics' => $metricsCount,
                        'alerts' => $alertsCount,
                        'thresholds' => $thresholdsCount
                    ] : []
                ]);
            }

            return redirect()
                ->route('admin.metric-types.index')
                ->with('success', 'Typ metryki usunięty.');

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Error deleting metric type',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Błąd podczas usuwania typu metryki.');
        }
    }

    /**
     * @OA\Get(
     *      path="/api/metric-types/stats",
     *      operationId="getWithStats",
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