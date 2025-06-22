<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConnectionStatusResource;
use App\Models\ConnectionStatus;
use App\Models\Host;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Connection Status",
 *     description="API Endpoints for managing host connection status (UC23)"
 * )
 */
class ConnectionStatusController extends Controller
{
    #region Properties
    
    /// <summary>
    /// Validation rules for connection status data
    /// </summary>
    private array $validationRules = [
        'host_id' => 'required|integer|exists:hosts,host_id',
        'status' => 'required|in:Online,Offline,Unknown',
        'last_check_date' => 'required|date',
        'error_message' => 'nullable|string|max:500',
        'response_time' => 'nullable|integer|min:0|max:30000'
    ];

    #endregion
    
    #region Methods

    /**
     * @OA\Get(
     *      path="/api/connection-statuses",
     *      operationId="getConnectionStatusesList",
     *      tags={"Connection Status"},
     *      summary="Get list of connection statuses (UC23)",
     *      description="Returns paginated list of connection statuses with filtering",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host_id",
     *          description="Filter by host ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          description="Filter by connection status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", enum={"Online", "Offline", "Unknown"})
     *      ),
     *      @OA\Parameter(
     *          name="date_from",
     *          description="Filter from date",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="date_to",
     *          description="Filter to date",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", format="date-time")
     *      ),
     *      @OA\Parameter(
     *          name="latest_only",
     *          description="Get only latest status per host",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="boolean", default=false)
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Number of items per page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", default=50)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ConnectionStatus")),
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
    /// Display a listing of connection statuses with filtering and pagination (UC23)
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <returns>JsonResponse</returns>
    public function index(Request $request): JsonResponse
    {
        $query = ConnectionStatus::with('host');

        // Filter by host
        if ($request->has('host_id')) {
            $query->where('host_id', $request->host_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('last_check_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('last_check_date', '<=', $request->date_to);
        }

        // Default: only recent statuses (last 24h)
        if (!$request->has('date_from') && !$request->has('date_to')) {
            $query->where('last_check_date', '>=', Carbon::now()->subDay());
        }

        // Get only latest status per host if requested
        if ($request->get('latest_only', false)) {
            $query->whereIn('status_id', function($subQuery) { // NAPRAWKA: status_id
                $subQuery->selectRaw('MAX(status_id)')
                         ->from('connection_statuses')
                         ->groupBy('host_id');
            });
        }

        $query->orderBy('last_check_date', 'desc');
        $statuses = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'data' => ConnectionStatusResource::collection($statuses),
            'meta' => [
                'current_page' => $statuses->currentPage(),
                'per_page' => $statuses->perPage(),
                'total' => $statuses->total(),
                'last_page' => $statuses->lastPage()
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
     *      path="/api/connection-statuses",
     *      operationId="storeConnectionStatus",
     *      tags={"Connection Status"},
     *      summary="Store new connection status (UC23)",
     *      description="Record new connection status check result",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"host_id", "status", "last_check_date"},
     *              @OA\Property(property="host_id", type="integer", example=1),
     *              @OA\Property(property="status", type="string", enum={"Online", "Offline", "Unknown"}, example="Online"),
     *              @OA\Property(property="last_check_date", type="string", format="date-time", example="2025-06-22T10:30:00Z"),
     *              @OA\Property(property="error_message", type="string", nullable=true, example=null),
     *              @OA\Property(property="response_time", type="integer", nullable=true, example=145)
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Connection status recorded successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/ConnectionStatus")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    /// <summary>
    /// Store a newly created connection status (UC23)
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

        try {
            // NAPRAWKA: Format daty MySQL
            $lastCheckDate = Carbon::parse($request->last_check_date)->format('Y-m-d H:i:s');

            $connectionStatus = ConnectionStatus::create([
                'host_id' => $request->host_id,
                'status' => $request->status,
                'last_check_date' => $lastCheckDate,
                'error_message' => $request->error_message,
                'response_time' => $request->response_time
            ]);

            // Update host's last_contact_date if status is Online
            if ($request->status === 'Online') {
                Host::where('host_id', $request->host_id)
                    ->update(['last_contact_date' => $lastCheckDate]);
            }

            $connectionStatus->load('host');

            return response()->json([
                'message' => 'Connection status recorded successfully',
                'data' => new ConnectionStatusResource($connectionStatus)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record connection status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/connection-statuses/{connectionStatus}",
     *      operationId="showConnectionStatus",
     *      tags={"Connection Status"},
     *      summary="Get specific connection status",
     *      description="Returns detailed information about specific connection status",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="connectionStatus",
     *          description="Connection Status ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Connection status details",
     *          @OA\JsonContent(
     *              @OA\Property(property="data", ref="#/components/schemas/ConnectionStatus")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Connection status not found")
     * )
     */
    /// <summary>
    /// Display the specified connection status
    /// </summary>
    /// <param name="connectionStatus">ConnectionStatus model instance</param>
    /// <returns>JsonResponse</returns>
    public function show(ConnectionStatus $connectionStatus): JsonResponse
    {
        $connectionStatus->load('host');
        
        return response()->json([
            'data' => new ConnectionStatusResource($connectionStatus)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ConnectionStatus $connectionStatus)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ConnectionStatus $connectionStatus)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConnectionStatus $connectionStatus)
    {
        //
    }

     /**
     * @OA\Get(
     *      path="/api/connection-statuses/latest",
     *      operationId="getLatestConnectionStatuses",
     *      tags={"Connection Status"},
     *      summary="Get latest connection status for all hosts (UC23)",
     *      description="Returns latest connection status for each monitored host",
     *      security={{"sanctum":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="Latest connection statuses",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ConnectionStatus")),
     *              @OA\Property(property="summary", type="object",
     *                  @OA\Property(property="total_hosts", type="integer", example=5),
     *                  @OA\Property(property="online_hosts", type="integer", example=3),
     *                  @OA\Property(property="offline_hosts", type="integer", example=1),
     *                  @OA\Property(property="unknown_hosts", type="integer", example=1),
     *                  @OA\Property(property="generated_at", type="string", format="date-time")
     *              )
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get latest connection status for all hosts (UC23)
    /// </summary>
    /// <returns>JsonResponse</returns>
    public function getLatestStatuses(): JsonResponse
    {
        $latestStatuses = ConnectionStatus::with('host')
            ->whereIn('status_id', function($query) { // NAPRAWKA: status_id
                $query->selectRaw('MAX(status_id)')
                      ->from('connection_statuses')
                      ->groupBy('host_id');
            })
            ->orderBy('last_check_date', 'desc')
            ->get();

        return response()->json([
            'data' => ConnectionStatusResource::collection($latestStatuses),
            'summary' => [
                'total_hosts' => $latestStatuses->count(),
                'online_hosts' => $latestStatuses->where('status', 'Online')->count(),
                'offline_hosts' => $latestStatuses->where('status', 'Offline')->count(),
                'unknown_hosts' => $latestStatuses->where('status', 'Unknown')->count(),
                'generated_at' => Carbon::now()->toISOString()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/connection-statuses/host/{hostId}/statistics",
     *      operationId="getHostConnectionStatistics",
     *      tags={"Connection Status"},
     *      summary="Get connection statistics for specific host",
     *      description="Returns detailed connection statistics for host",
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
     *          description="Host connection statistics",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="host", type="object",
     *                  @OA\Property(property="host_id", type="integer"),
     *                  @OA\Property(property="host_name", type="string"),
     *                  @OA\Property(property="ip_address", type="string")
     *              ),
     *              @OA\Property(property="current_status", type="object",
     *                  @OA\Property(property="status", type="string"),
     *                  @OA\Property(property="last_check", type="string", format="date-time"),
     *                  @OA\Property(property="response_time", type="integer")
     *              ),
     *              @OA\Property(property="last_24h", type="object",
     *                  @OA\Property(property="total_checks", type="integer"),
     *                  @OA\Property(property="online_checks", type="integer"),
     *                  @OA\Property(property="offline_checks", type="integer"),
     *                  @OA\Property(property="average_response_time", type="number", format="float"),
     *                  @OA\Property(property="uptime_percentage", type="number", format="float")
     *              ),
     *              @OA\Property(property="last_7_days", type="object",
     *                  @OA\Property(property="total_checks", type="integer"),
     *                  @OA\Property(property="online_checks", type="integer"),
     *                  @OA\Property(property="offline_checks", type="integer"),
     *                  @OA\Property(property="average_response_time", type="number", format="float"),
     *                  @OA\Property(property="uptime_percentage", type="number", format="float")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Get connection statistics for specific host
    /// </summary>
    /// <param name="hostId">Host ID jako string</param>
    /// <returns>JsonResponse</returns>
    public function getHostStatistics(string $hostId): JsonResponse // NAPRAWKA: string
    {
        $host = Host::find($hostId);
        if (!$host) {
            return response()->json(['message' => 'Host not found'], 404);
        }

        $last24h = Carbon::now()->subDay();
        $last7days = Carbon::now()->subDays(7);

        $statuses = ConnectionStatus::where('host_id', $hostId)
                                  ->where('last_check_date', '>=', $last7days)
                                  ->orderBy('last_check_date', 'desc')
                                  ->get();

        $last24hStatuses = $statuses->where('last_check_date', '>=', $last24h);

        $statistics = [
            'host' => [
                'host_id' => $host->host_id,
                'host_name' => $host->host_name,
                'ip_address' => $host->ip_address
            ],
            'current_status' => $statuses->first() ? [
                'status' => $statuses->first()->status,
                'last_check' => $statuses->first()->last_check_date,
                'response_time' => $statuses->first()->response_time
            ] : null,
            'last_24h' => [
                'total_checks' => $last24hStatuses->count(),
                'online_checks' => $last24hStatuses->where('status', 'Online')->count(),
                'offline_checks' => $last24hStatuses->where('status', 'Offline')->count(),
                'average_response_time' => $last24hStatuses->where('status', 'Online')->avg('response_time'),
                'uptime_percentage' => $last24hStatuses->count() > 0 ? 
                    round(($last24hStatuses->where('status', 'Online')->count() / $last24hStatuses->count()) * 100, 2) : 0
            ],
            'last_7_days' => [
                'total_checks' => $statuses->count(),
                'online_checks' => $statuses->where('status', 'Online')->count(),
                'offline_checks' => $statuses->where('status', 'Offline')->count(),
                'average_response_time' => $statuses->where('status', 'Online')->avg('response_time'),
                'uptime_percentage' => $statuses->count() > 0 ? 
                    round(($statuses->where('status', 'Online')->count() / $statuses->count()) * 100, 2) : 0
            ]
        ];

        return response()->json($statistics);
    }

    /**
     * @OA\Delete(
     *      path="/api/connection-statuses/cleanup",
     *      operationId="cleanupOldConnectionStatuses",
     *      tags={"Connection Status"},
     *      summary="Delete old connection statuses (maintenance)",
     *      description="Delete connection statuses older than specified days",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"days_to_keep"},
     *              @OA\Property(property="days_to_keep", type="integer", example=30, minimum=1, maximum=365)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Cleanup completed",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="deleted_records", type="integer"),
     *              @OA\Property(property="cutoff_date", type="string", format="date-time")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    /// <summary>
    /// Delete old connection statuses (cleanup endpoint)
    /// </summary>
    /// <param name="request">HTTP request</param>
    /// <returns>JsonResponse</returns>
    public function cleanup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days_to_keep' => 'required|integer|min:1|max:365'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $cutoffDate = Carbon::now()->subDays($request->days_to_keep);
        
        $deletedCount = ConnectionStatus::where('last_check_date', '<', $cutoffDate)->delete();

        return response()->json([
            'message' => 'Connection status cleanup completed',
            'deleted_records' => $deletedCount,
            'cutoff_date' => $cutoffDate->toISOString()
        ]);
    }

    #endregion
}
