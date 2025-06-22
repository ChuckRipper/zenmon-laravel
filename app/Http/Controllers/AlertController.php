<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Http\Resources\AlertResource;
use App\Http\Resources\AlertCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
     *      summary="Get list of alerts",
     *      description="Returns list of alerts with pagination and filtering",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="status",
     *          description="Filter by alert status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"Active", "Acknowledged", "Resolved"}
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
     *              @OA\Property(property="meta", type="object",
     *                  @OA\Property(property="current_page", type="integer"),
     *                  @OA\Property(property="per_page", type="integer"),
     *                  @OA\Property(property="total", type="integer"),
     *                  @OA\Property(property="last_page", type="integer")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *      )
     * )
     */
    /// <summary>
    /// Display a listing of alerts with filtering and pagination (UC44)
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>AlertCollection</returns>
    public function index(Request $request): AlertCollection
    {
        $query = Alert::with(['host', 'metricType', 'acknowledgedByUser', 'closedByUser']);

        // Filtrowanie zgodnie z UC44: hostId, status, level, dateFrom, dateTo
        if ($request->has('hostId')) {
            $query->where('host_id', $request->hostId);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('level')) {
            $query->where('alert_level', $request->level);
        }

        if ($request->has('dateFrom')) {
            $query->where('created_at', '>=', $request->dateFrom);
        }

        if ($request->has('dateTo')) {
            $query->where('created_at', '<=', $request->dateTo);
        }

        // Sortowanie (najnowsze pierwsze)
        $query->orderBy('created_at', 'desc');

        // Paginacja zgodnie z UC44
        $pageSize = $request->get('pageSize', 15);
        $alerts = $query->paginate($pageSize);

        return new AlertCollection($alerts);
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
}