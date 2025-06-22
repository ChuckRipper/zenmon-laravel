<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertThresholdResource;
use App\Models\AlertThreshold;
use App\Models\Host;
use App\Models\MetricType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Alert Thresholds",
 *     description="API Endpoints for managing alert thresholds (UC40)"
 * )
 */
class AlertThresholdController extends Controller
{
    #region Properties
    
    /// <summary>
    /// Validation rules for alert threshold data
    /// </summary>
    private array $validationRules = [
        'metric_type_id' => 'required|integer|exists:metric_types,metric_type_id',
        'host_id' => 'nullable|integer|exists:hosts,host_id',
        'warning_threshold' => 'required|numeric|min:0',
        'critical_threshold' => 'required|numeric|min:0',
        'is_active' => 'boolean'
    ];

    #endregion

    #region Methods

    /**
     * @OA\Get(
     *      path="/api/alert-thresholds",
     *      operationId="getAlertThresholdsList",
     *      tags={"Alert Thresholds"},
     *      summary="Get list of alert thresholds (UC40)",
     *      description="Returns paginated list of alert thresholds with filtering",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="metric_type_id",
     *          description="Filter by metric type ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="host_id",
     *          description="Filter by host ID (use 'global' for global thresholds)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="threshold_type",
     *          description="Filter by threshold type",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", enum={"warning", "critical"})
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
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AlertThreshold")),
     *              @OA\Property(property="meta", type="object",
     *                  @OA\Property(property="current_page", type="integer"),
     *                  @OA\Property(property="per_page", type="integer"),
     *                  @OA\Property(property="total", type="integer"),
     *                  @OA\Property(property="last_page", type="integer")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    /// <summary>
    /// Display a listing of alert thresholds with filtering and pagination (UC40)
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <returns>JsonResponse</returns>
     public function index(Request $request): JsonResponse
    {
        $query = AlertThreshold::with(['metricType', 'host'])
                               ->where('is_active', true);

        // Filter by metric type
        if ($request->has('metric_type_id')) {
            $query->where('metric_type_id', $request->metric_type_id);
        }

        // Filter by host (null = global thresholds)
        if ($request->has('host_id')) {
            if ($request->host_id === 'global') {
                $query->whereNull('host_id');
            } else {
                $query->where('host_id', $request->host_id);
            }
        }

        // Filter by threshold level
        if ($request->has('threshold_type')) {
            if ($request->threshold_type === 'warning') {
                $query->whereNotNull('warning_threshold');
            } elseif ($request->threshold_type === 'critical') {
                $query->whereNotNull('critical_threshold');
            }
        }

        $query->orderBy('metric_type_id')->orderBy('host_id');
        $thresholds = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => AlertThresholdResource::collection($thresholds),
            'meta' => [
                'current_page' => $thresholds->currentPage(),
                'per_page' => $thresholds->perPage(),
                'total' => $thresholds->total(),
                'last_page' => $thresholds->lastPage()
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
     *      path="/api/alert-thresholds",
     *      operationId="storeAlertThreshold",
     *      tags={"Alert Thresholds"},
     *      summary="Create new alert threshold (UC40)",
     *      description="Create new alert threshold configuration",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"metric_type_id", "warning_threshold", "critical_threshold"},
     *              @OA\Property(property="metric_type_id", type="integer", example=1, description="Metric type ID"),
     *              @OA\Property(property="host_id", type="integer", nullable=true, example=1, description="Host ID (null for global threshold)"),
     *              @OA\Property(property="warning_threshold", type="number", format="float", example=70.0),
     *              @OA\Property(property="critical_threshold", type="number", format="float", example=90.0),
     *              @OA\Property(property="is_active", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Alert threshold created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/AlertThreshold")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Store a newly created alert threshold (UC40)
    /// </summary>
    /// <param name="request">HTTP request</param>
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

        // Check if warning threshold is less than critical threshold
        if ($request->warning_threshold >= $request->critical_threshold) {
            return response()->json([
                'message' => 'Warning threshold must be less than critical threshold',
                'errors' => [
                    'warning_threshold' => ['Warning threshold must be less than critical threshold']
                ]
            ], 422);
        }

        // Check for duplicate thresholds (same metric_type + host combination)
        $existingThreshold = AlertThreshold::where('metric_type_id', $request->metric_type_id)
                                          ->where('host_id', $request->host_id)
                                          ->where('is_active', true)
                                          ->first();

        if ($existingThreshold) {
            return response()->json([
                'message' => 'Alert threshold already exists for this metric type and host',
                'errors' => [
                    'threshold' => ['Threshold already exists for this combination']
                ]
            ], 422);
        }

        try {
            $threshold = AlertThreshold::create([
                'metric_type_id' => $request->metric_type_id,
                'host_id' => $request->host_id,
                'warning_threshold' => $request->warning_threshold,
                'critical_threshold' => $request->critical_threshold,
                'is_active' => $request->get('is_active', true),
                'created_by_user_id' => auth()->id() // NAPRAWKA
            ]);

            $threshold->load(['metricType', 'host']);

            return response()->json([
                'message' => 'Alert threshold created successfully',
                'data' => new AlertThresholdResource($threshold)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create alert threshold',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/alert-thresholds/{alertThreshold}",
     *      operationId="showAlertThreshold",
     *      tags={"Alert Thresholds"},
     *      summary="Get specific alert threshold",
     *      description="Returns detailed information about specific alert threshold",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="alertThreshold",
     *          description="Alert Threshold ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Alert threshold details",
     *          @OA\JsonContent(
     *              @OA\Property(property="data", ref="#/components/schemas/AlertThreshold")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Alert threshold not found")
     * )
     */
    /// <summary>
    /// Display the specified alert threshold
    /// </summary>
    /// <param name="alertThreshold">AlertThreshold model instance</param>
    /// <returns>JsonResponse</returns>
    public function show(AlertThreshold $alertThreshold): JsonResponse
    {
        $alertThreshold->load(['metricType', 'host']);
        
        return response()->json([
            'data' => new AlertThresholdResource($alertThreshold)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AlertThreshold $alertThreshold)
    {
        //
    }

    /**
     * @OA\Put(
     *      path="/api/alert-thresholds/{alertThreshold}",
     *      operationId="updateAlertThreshold",
     *      tags={"Alert Thresholds"},
     *      summary="Update alert threshold (UC40)",
     *      description="Update existing alert threshold configuration",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="alertThreshold",
     *          description="Alert Threshold ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="warning_threshold", type="number", format="float", example=75.0),
     *              @OA\Property(property="critical_threshold", type="number", format="float", example=95.0),
     *              @OA\Property(property="is_active", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Alert threshold updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/AlertThreshold")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    /// <summary>
    /// Update the specified alert threshold (UC40)
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <param name="alertThreshold">AlertThreshold model instance</param>
    /// <returns>JsonResponse</returns>
    public function update(Request $request, AlertThreshold $alertThreshold): JsonResponse
    {
        $rules = $this->validationRules;
        // Make fields optional for updates
        $rules['metric_type_id'] = 'sometimes|integer|exists:metric_types,metric_type_id';
        $rules['host_id'] = 'sometimes|nullable|integer|exists:hosts,host_id';
        $rules['warning_threshold'] = 'sometimes|numeric|min:0';
        $rules['critical_threshold'] = 'sometimes|numeric|min:0';

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check threshold order if both are provided
        $warningThreshold = $request->get('warning_threshold', $alertThreshold->warning_threshold);
        $criticalThreshold = $request->get('critical_threshold', $alertThreshold->critical_threshold);

        if ($warningThreshold >= $criticalThreshold) {
            return response()->json([
                'message' => 'Warning threshold must be less than critical threshold',
                'errors' => [
                    'warning_threshold' => ['Warning threshold must be less than critical threshold']
                ]
            ], 422);
        }

        try {
            $alertThreshold->update($request->only([
                'metric_type_id', 'host_id', 'warning_threshold', 
                'critical_threshold', 'is_active'
            ]));

            $alertThreshold->load(['metricType', 'host']);

            return response()->json([
                'message' => 'Alert threshold updated successfully',
                'data' => new AlertThresholdResource($alertThreshold)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update alert threshold',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *      path="/api/alert-thresholds/{alertThreshold}",
     *      operationId="deleteAlertThreshold",
     *      tags={"Alert Thresholds"},
     *      summary="Delete alert threshold",
     *      description="Deactivate alert threshold (soft delete)",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="alertThreshold",
     *          description="Alert Threshold ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Alert threshold deactivated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Alert threshold not found")
     * )
     */
    /// <summary>
    /// Remove the specified alert threshold (soft delete)
    /// </summary>
    /// <param name="alertThreshold">AlertThreshold model instance</param>
    /// <returns>JsonResponse</returns>
    public function destroy(AlertThreshold $alertThreshold): JsonResponse
    {
        try {
            // Soft delete by setting is_active to false
            $alertThreshold->update(['is_active' => false]);

            return response()->json([
                'message' => 'Alert threshold deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to deactivate alert threshold',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #endregion
}
