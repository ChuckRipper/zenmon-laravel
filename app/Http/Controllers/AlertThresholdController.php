<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertThresholdResource;
use App\Models\AlertThreshold;
use App\Models\Host;
use App\Models\MetricType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Alert Thresholds",
 *     description="API Endpoints for managing alert thresholds configuration (UC40)"
 * )
 */
class AlertThresholdController extends Controller
{
    #region Properties

    /// <summary>
    /// Validation rules for alert threshold data
    /// </summary>
    private array $validationRules = [
        'host_id' => 'nullable|integer|exists:hosts,host_id',
        'metric_type_id' => 'required|integer|exists:metric_types,metric_type_id',
        'warning_threshold' => 'required|numeric|min:0',
        'critical_threshold' => 'required|numeric|min:0',
        'is_active' => 'sometimes|boolean',
        'created_by_user_id' => 'sometimes|integer|exists:users,id'
    ];

    #endregion

    #region Methods

    /**
     * @OA\Get(
     *      path="/api/alert-thresholds",
     *      operationId="getAlertThresholdsList",
     *      tags={"Alert Thresholds"},
     *      summary="Get list of alert thresholds (UC40)",
     *      description="Returns list of alert thresholds with filtering options",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host_id",
     *          description="Filter by host ID (null for global thresholds)",
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
     *          name="is_active",
     *          description="Filter by active status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="global_only",
     *          description="Show only global thresholds (host_id = null)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AlertThreshold"))
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthorized")
     * )
     */
    /// <summary>
    /// Display a listing of alert thresholds
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function index(Request $request): JsonResponse
    {
        $query = AlertThreshold::with(['host', 'metricType', 'createdByUser']);

        // Filtrowanie według host_id
        if ($request->has('host_id')) {
            $query->where('host_id', $request->host_id);
        }

        // Filtrowanie tylko globalne progi
        if ($request->boolean('global_only')) {
            $query->whereNull('host_id');
        }

        // Filtrowanie według metric_type_id
        if ($request->has('metric_type_id')) {
            $query->where('metric_type_id', $request->metric_type_id);
        }

        // Filtrowanie według statusu aktywności
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $thresholds = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => AlertThresholdResource::collection($thresholds)
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
     *      operationId="createAlertThreshold",
     *      tags={"Alert Thresholds"},
     *      summary="Create new alert threshold (UC40)",
     *      description="Create new threshold configuration for alerts. Set host_id to null for global thresholds.",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"metric_type_id", "warning_threshold", "critical_threshold"},
     *              @OA\Property(property="host_id", type="integer", description="Host ID (null for global threshold)", nullable=true),
     *              @OA\Property(property="metric_type_id", type="integer", description="Metric Type ID"),
     *              @OA\Property(property="warning_threshold", type="number", description="Warning threshold value"),
     *              @OA\Property(property="critical_threshold", type="number", description="Critical threshold value"),
     *              @OA\Property(property="is_active", type="boolean", default=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Threshold created successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/AlertThreshold")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=409, description="Threshold already exists")
     * )
     */
    /// <summary>
    /// Store a newly created alert threshold in storage
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge($this->validationRules, [
            'warning_threshold' => 'required|numeric|min:0|lt:critical_threshold',
            'critical_threshold' => 'required|numeric|min:0|gt:warning_threshold'
        ]));

        // Sprawdzenie czy próg dla tej kombinacji host+metric_type już istnieje
        $existingThreshold = AlertThreshold::where('host_id', $validated['host_id'])
            ->where('metric_type_id', $validated['metric_type_id'])
            ->first();

        if ($existingThreshold) {
            $scopeType = $validated['host_id'] ? 'host-specific' : 'global';
            return response()->json([
                'message' => "Alert threshold for this metric type already exists ({$scopeType}). Use PUT to update."
            ], 409);
        }

        // Sprawdzenie czy host i metric_type istnieją
        if ($validated['host_id']) {
            Host::where('host_id', $validated['host_id'])->firstOrFail();
        }
        MetricType::where('metric_type_id', $validated['metric_type_id'])->firstOrFail();

        // Ustawienie domyślnych wartości
        $validated['created_by_user_id'] = Auth::id();
        $validated['is_active'] = $validated['is_active'] ?? true;

        $threshold = AlertThreshold::create($validated);
        $threshold->load(['host', 'metricType', 'createdByUser']);

        $scopeMessage = $validated['host_id']
            ? "for host '{$threshold->host->host_name}'"
            : "globally";

        return response()->json([
            'message' => "Alert threshold created successfully {$scopeMessage}",
            'data' => new AlertThresholdResource($threshold)
        ], 201);
    }

    /**
     * @OA\Get(
     *      path="/api/alert-thresholds/{threshold_id}",
     *      operationId="showAlertThreshold",
     *      tags={"Alert Thresholds"},
     *      summary="Get specific alert threshold (UC40)",
     *      description="Get threshold details by ID",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="threshold_id",
     *          description="Threshold ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/AlertThreshold")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Threshold not found")
     * )
     */
    /// <summary>
    /// Display the specified alert threshold
    /// </summary>
    /// <param>int $threshold_id</param>
    /// <returns>AlertThresholdResource</returns>
    public function show(int $threshold_id): AlertThresholdResource
    {
        $alertThreshold = AlertThreshold::with(['host', 'metricType', 'createdByUser'])
            ->where('threshold_id', $threshold_id)
            ->firstOrFail();

        return new AlertThresholdResource($alertThreshold);
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
     *      path="/api/alert-thresholds/{threshold_id}",
     *      operationId="updateAlertThreshold",
     *      tags={"Alert Thresholds"},
     *      summary="Update alert threshold (UC40)",
     *      description="Update threshold configuration",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="threshold_id",
     *          description="Threshold ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="warning_threshold", type="number", description="Warning threshold value"),
     *              @OA\Property(property="critical_threshold", type="number", description="Critical threshold value"),
     *              @OA\Property(property="is_active", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Threshold updated successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/AlertThreshold")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="Threshold not found")
     * )
     */
    /// <summary>
    /// Update the specified alert threshold in storage
    /// </summary>
    /// <param>Request $request</param>
    /// <param>int $threshold_id</param>
    /// <returns>JsonResponse</returns>
    public function update(Request $request, int $threshold_id): JsonResponse
    {
        $alertThreshold = AlertThreshold::where('threshold_id', $threshold_id)->firstOrFail();

        // Usunięcie pól które nie mogą być zmieniane w update
        $updateRules = $this->validationRules;
        unset($updateRules['host_id'], $updateRules['metric_type_id'], $updateRules['created_by_user_id']);

        // Dodanie walidacji relacji warning < critical
        $updateRules['warning_threshold'] = 'sometimes|numeric|min:0|lt:critical_threshold';
        $updateRules['critical_threshold'] = 'sometimes|numeric|min:0|gt:warning_threshold';

        $validated = $request->validate($updateRules);

        $alertThreshold->update($validated);
        $alertThreshold->load(['host', 'metricType', 'createdByUser']);

        $scopeMessage = $alertThreshold->host_id
            ? "for host '{$alertThreshold->host->host_name}'"
            : "globally";

        return response()->json([
            'message' => "Alert threshold updated successfully {$scopeMessage}",
            'data' => new AlertThresholdResource($alertThreshold)
        ]);
    }

    /**
     * @OA\Delete(
     *      path="/api/alert-thresholds/{threshold_id}",
     *      operationId="deleteAlertThreshold",
     *      tags={"Alert Thresholds"},
     *      summary="Delete alert threshold (UC40)",
     *      description="Delete threshold configuration",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="threshold_id",
     *          description="Threshold ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Threshold deleted successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Threshold not found")
     * )
     */
    /// <summary>
    /// Remove the specified alert threshold from storage
    /// </summary>
    /// <param>int $threshold_id</param>
    /// <returns>JsonResponse</returns>
    public function destroy(int $threshold_id): JsonResponse
    {
        $alertThreshold = AlertThreshold::with(['host', 'metricType'])
            ->where('threshold_id', $threshold_id)
            ->firstOrFail();

        $metricTypeName = $alertThreshold->metricType->metric_name;
        $scopeMessage = $alertThreshold->host_id
            ? "for host '{$alertThreshold->host->host_name}'"
            : "globally";

        $alertThreshold->delete();

        return response()->json([
            'message' => "Alert threshold for '{$metricTypeName}' deleted successfully {$scopeMessage}"
        ]);
    }

    #endregion
}