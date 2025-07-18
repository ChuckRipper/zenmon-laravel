<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertThreshold;
use App\Models\Metric;
use App\Http\Resources\AlertResource;
use App\Http\Resources\AlertCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Alerts",
 *     description="API Endpoints for managing alerts"
 * )
 */
class AlertController extends Controller
{
    #region Methods

    /**
     * @OA\Get(
     *      path="/api/alerts",
     *      operationId="getAlertsList",
     *      tags={"Alerts"},
     *      summary="Get list of alerts with filtering (UC34)",
     *      description="Returns paginated list of alerts with filtering options for dashboard and alert history",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="status",
     *          description="Filter by alert status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"Active", "Acknowledged", "Closed"}
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="host_id",
     *          description="Filter by host ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="alert_level",
     *          description="Filter by alert level",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"Warning", "Critical"}
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="metric_type_id",
     *          description="Filter by metric type",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="from_date",
     *          description="Start date for filtering",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="to_date",
     *          description="End date for filtering",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Number of alerts per page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", default=20)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Alert")),
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
    /// Pobieranie listy alertów z filtrowaniem i paginacją (UC34)
    /// </summary>
    /// <param name="request">Żądanie HTTP z parametrami filtrowania</param>
    /// <returns>Paginowana lista alertów w formacie JSON</returns>
    public function index(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:Active,Acknowledged,Closed',
            'host_id' => 'sometimes|integer|exists:hosts,host_id',
            'alert_level' => 'sometimes|string|in:Warning,Critical',
            'metric_type_id' => 'sometimes|integer|exists:metric_types,metric_type_id',
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
            'per_page' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Alert::with(['host', 'metricType', 'acknowledgedByUser', 'closedByUser']);

        // Filtrowanie według statusu
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtrowanie według host_id
        if ($request->has('host_id')) {
            $query->where('host_id', $request->host_id);
        }

        // Filtrowanie według poziomu alertu
        if ($request->has('alert_level')) {
            $query->where('alert_level', $request->alert_level);
        }

        // Filtrowanie według typu metryki
        if ($request->has('metric_type_id')) {
            $query->where('metric_type_id', $request->metric_type_id);
        }

        // Filtrowanie według zakresu dat
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        // Sortowanie według daty utworzenia (najnowsze pierwsze)
        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 20);
        $alerts = $query->paginate($perPage);

        return (new AlertCollection($alerts))->response();
    }

    /// <summary>
    /// Store a newly created alert
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host_id' => 'required|exists:hosts,host_id',
            'metric_type_id' => 'required|exists:metric_types,metric_type_id',
            'alert_level' => 'required|in:Warning,Critical',
            'alert_message' => 'required|string|max:1000',
            'current_value' => 'required|numeric',
            'threshold_value' => 'required|numeric'
        ]);

        $alert = Alert::create($validated);
        $alert->load(['host', 'metricType']);

        return response()->json([
            'message' => 'Alert created successfully',
            'data' => new AlertResource($alert)
        ], 201);
    }

    /// <summary>
    /// Display the specified alert
    /// </summary>
    /// <param>Alert $alert</param>
    /// <returns>AlertResource</returns>
    public function show(Alert $alert): AlertResource
    {
        $alert->load(['host', 'metricType', 'acknowledgedByUser', 'closedByUser']);

        // Upewnij się że alert ma wszystkie potrzebne relacje
        if (!$alert->host || !$alert->metricType) {
            return response()->json([
                'error' => 'Alert relationship data missing'
            ], 500);
        }
        
        return new AlertResource($alert);
    }

    /// <summary>
    /// Update the specified alert (UC43: Acknowledge/Close)
    /// </summary>
    /// <param>Request $request</param>
    /// <param>Alert $alert</param>
    /// <returns>JsonResponse</returns>
    public function update(Request $request, Alert $alert): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:Acknowledged,Closed',
            'acknowledged_by_user_id' => 'sometimes|exists:users,id',
            'closed_by_user_id' => 'sometimes|exists:users,id',
            'close_comment' => 'sometimes|string|max:1000'
        ]);

        // UC43: Potwierdzanie alertów
        if ($request->status === 'Acknowledged') {
            $validated['acknowledged_date'] = now();
        }

        // UC43: Zamykanie alertów
        if ($request->status === 'Closed') {
            $validated['closed_date'] = now();
            if (!$request->has('close_comment')) {
                return response()->json([
                    'message' => 'Close comment is required when closing alert'
                ], 422);
            }
        }

        $alert->update($validated);
        $alert->load(['host', 'metricType', 'acknowledgedByUser', 'closedByUser']);

        return response()->json([
            'message' => 'Alert updated successfully',
            'data' => new AlertResource($alert)
        ]);
    }

    /// <summary>
    /// Remove the specified alert
    /// </summary>
    /// <param>Alert $alert</param>
    /// <returns>JsonResponse</returns>
    public function destroy(Alert $alert): JsonResponse
    {
        $alert->delete();

        return response()->json([
            'message' => 'Alert deleted successfully'
        ]);
    }
    #endregion

    #region Public Methods

    /**
     * @OA\Get(
     *      path="/api/public/alerts/summary",
     *      operationId="getPublicAlertSummary",
     *      tags={"Public"},
     *      summary="Get public alert statistics",
     *      description="Returns basic alert summary statistics - no authentication required",
     *      @OA\Response(
     *          response=200,
     *          description="Alert summary statistics",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="total_alerts", type="integer"),
     *              @OA\Property(property="active_alerts", type="integer"),
     *              @OA\Property(property="critical_alerts", type="integer"),
     *              @OA\Property(property="timestamp", type="string")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get public alert summary statistics
    /// </summary>
    /// <returns>JsonResponse</returns>
    public function getPublicAlertSummary(): JsonResponse
    {
        $totalAlerts = \App\Models\Alert::count();
        $activeAlerts = \App\Models\Alert::where('status', 'Active')->count();
        $criticalAlerts = \App\Models\Alert::where('status', 'Active')
                                          ->where('alert_level', 'Critical')
                                          ->count();
        $alertsLast24h = \App\Models\Alert::where('created_at', '>=', now()->subHours(24))->count();

        return response()->json([
            'total_alerts' => $totalAlerts,
            'active_alerts' => $activeAlerts,
            'critical_alerts' => $criticalAlerts,
            'warning_alerts' => $activeAlerts - $criticalAlerts,
            'alerts_last_24h' => $alertsLast24h,
            'system_health' => $criticalAlerts > 0 ? 'critical' : ($activeAlerts > 0 ? 'warning' : 'healthy'),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/alerts/{alert}/acknowledge",
     *      operationId="acknowledgeAlert",
     *      tags={"Alerts"},
     *      summary="Acknowledge alert (UC43)",
     *      description="Mark alert as acknowledged by current user",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="alert",
     *          description="Alert ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Alert acknowledged successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/Alert")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=404, description="Alert not found")
     * )
     */
    /// <summary>
    /// Acknowledge alert (UC43)
    /// </summary>
    /// <param>Request $request</param>
    /// <param>Alert $alert</param>
    /// <returns>JsonResponse</returns>
    public function acknowledge(Request $request, Alert $alert): JsonResponse
    {
        $alert->update([
            'status' => 'Acknowledged',
            'acknowledged_date' => now(),
            'acknowledged_by_user_id' => $request->user()->id
        ]);

        $alert->load(['host', 'metricType', 'acknowledgedByUser', 'closedByUser']);

        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged successfully',
            'data' => new AlertResource($alert)
        ]);
    }

    /**
     * @OA\Put(
     *      path="/api/alerts/{alert}/close",
     *      operationId="closeAlert",
     *      tags={"Alerts"},
     *      summary="Close alert with comment",
     *      description="Close alert with required comment",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="alert",
     *          description="Alert ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"close_comment"},
     *              @OA\Property(property="close_comment", type="string", example="Issue resolved after server restart"),
     *              @OA\Property(property="closed_by_user_id", type="integer", example=1)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Alert closed successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/Alert")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error - comment required"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=404, description="Alert not found")
     * )
     */
    /// <summary>
    /// Close alert with comment
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <param name="alert">Alert instance</param>
    /// <returns>JsonResponse</returns>
    public function close(Request $request, Alert $alert): JsonResponse
    {
        $validated = $request->validate([
            'close_comment' => 'required|string|max:1000',
            'closed_by_user_id' => 'sometimes|exists:users,id'
        ]);

        $closedByUserId = $validated['closed_by_user_id'] ?? $request->user()->id;

        $alert->update([
            'status' => 'Closed',
            'closed_at' => now(),
            'close_comment' => $validated['close_comment'],
            'closed_by_user_id' => $closedByUserId
        ]);

        $alert->load(['host', 'metricType', 'acknowledgedByUser', 'closedByUser']);

        return response()->json([
            'success' => true,
            'message' => 'Alert closed successfully',
            'data' => new AlertResource($alert)
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/alerts/{alert}/resolve",
     *      operationId="resolveAlert",
     *      tags={"Alerts"},
     *      summary="Resolve alert (admin only)",
     *      description="Mark alert as resolved automatically when conditions return to normal",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="alert",
     *          description="Alert ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Alert resolved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/Alert")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Admin role required"),
     *      @OA\Response(response=404, description="Alert not found")
     * )
     */
    /// <summary>
    /// Resolve alert (admin only)
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <param name="alert">Alert instance</param>
    /// <returns>JsonResponse</returns>
    public function resolve(Request $request, Alert $alert): JsonResponse
    {
        $alert->update([
            'status' => 'Resolved',
            'closed_at' => now()
        ]);

        $alert->load(['host', 'metricType', 'acknowledgedByUser', 'closedByUser']);

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved successfully',
            'data' => new AlertResource($alert)
        ]);
    }

    #endregion
}