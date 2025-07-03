<?php

namespace App\Http\Controllers;

use App\Models\Host;
use App\Models\HostConfiguration;
use App\Http\Resources\HostResource;
use App\Http\Resources\HostCollection;
use App\Http\Resources\MetricResource;
use App\Http\Resources\AlertResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Hosts",
 *     description="API Endpoints for managing monitored hosts (UC20, UC21, UC22, UC23)"
 * )
 */
class HostController extends Controller
{
    #region Methods
    
    /**
     * @OA\Get(
     *      path="/api/hosts",
     *      operationId="getHostsList",
     *      tags={"Hosts"},
     *      summary="Get list of monitored hosts (UC22)",
     *      description="Returns paginated list of hosts with filtering options",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="is_active",
     *          description="Filter by host active status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="operating_system",
     *          description="Filter by operating system",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="search",
     *          description="Search in host name or IP address",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Number of hosts per page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", default=20)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Host")),
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
    /// Display a listing of hosts
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>HostCollection</returns>
    public function index(Request $request): HostCollection
    {
        $query = Host::with(['configuration', 'connectionStatuses' => function($query) {
            $query->latest('last_check_date')->limit(1);
        }]);

        // Filtrowanie według aktywności
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filtrowanie według systemu operacyjnego
        if ($request->has('operating_system')) {
            $query->where('operating_system', $request->operating_system);
        }

        $hosts = $query->get();
        return new HostCollection($hosts);
    }

    /**
     * @OA\Post(
     *      path="/api/hosts",
     *      operationId="storeHost",
     *      tags={"Hosts"},
     *      summary="Create new monitored host (UC20)",
     *      description="Add new host to monitoring system",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"host_name", "ip_address"},
     *              @OA\Property(property="host_name", type="string", example="web-server-01", description="Unique host name"),
     *              @OA\Property(property="ip_address", type="string", example="192.168.1.100", description="Unique IP address"),
     *              @OA\Property(property="description", type="string", example="Production web server", description="Host description"),
     *              @OA\Property(property="operating_system", type="string", example="Ubuntu 22.04", description="Operating system")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Host created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Host created successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/Host")
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
    /// Store a newly created host in storage (UC20: Dodawanie nowego hosta)
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host_name' => 'required|string|max:100',
            'ip_address' => 'required|ip|unique:hosts,ip_address',
            'description' => 'nullable|string|max:500',
            'operating_system' => 'nullable|string|max:100',
            'agent_version' => 'nullable|string|max:20'
        ]);

        // UC20: Sprawdź połączenie z agentem (symulacja)
        $agentOnline = $this->testAgentConnection($validated['ip_address']);

        $host = Host::create($validated);

        // UC20: Utwórz domyślną konfigurację
        HostConfiguration::create([
            'host_id' => $host->host_id,
            'updated_by_user_id' => auth()->id()
        ]);

        $host->load(['configuration']);

        return response()->json([
            'message' => 'Host created successfully',
            'agent_status' => $agentOnline ? 'online' : 'offline',
            'data' => new HostResource($host)
        ], 201);
    }

    /**
     * @OA\Get(
     *      path="/api/hosts/{host}",
     *      operationId="showHost",
     *      tags={"Hosts"},
     *      summary="Get specific host details",
     *      description="Returns detailed information about specific host",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Host details",
     *          @OA\JsonContent(
     *              @OA\Property(property="data", ref="#/components/schemas/Host")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Display the specified host
    /// </summary>
    /// <param>Host $host</param>
    /// <returns>HostResource</returns>
    public function show(Host $host): HostResource
    {
        $host->load([
            'configuration.updatedByUser', 
            'connectionStatuses' => function($query) {
                $query->latest('last_check_date')->limit(5);
            },
            'alerts' => function($query) {
                $query->where('status', 'Active')->latest();
            },
            'monitoredDirectories' => function($query) {
                $query->where('is_active', true);
            }
        ]);
        
        return new HostResource($host);
    }

    /**
     * @OA\Put(
     *      path="/api/hosts/{host}",
     *      operationId="updateHost",
     *      tags={"Hosts"},
     *      summary="Update host information (UC21)",
     *      description="Update existing host details",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="host_name", type="string", example="web-server-01"),
     *              @OA\Property(property="ip_address", type="string", example="192.168.1.100"),
     *              @OA\Property(property="description", type="string", example="Updated description"),
     *              @OA\Property(property="operating_system", type="string", example="Ubuntu 22.04"),
     *              @OA\Property(property="is_active", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Host updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/Host")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error")
     * )
     */
    /// <summary>
    /// Update the specified host in storage
    /// </summary>
    /// <param>Request $request</param>
    /// <param>Host $host</param>
    /// <returns>JsonResponse</returns>
    public function update(Request $request, Host $host): JsonResponse
    {
        $validated = $request->validate([
            'host_name' => 'sometimes|string|max:100',
            'ip_address' => 'sometimes|ip|unique:hosts,ip_address,' . $host->host_id . ',host_id',
            'description' => 'sometimes|nullable|string|max:500',
            'operating_system' => 'sometimes|nullable|string|max:100',
            'agent_version' => 'sometimes|nullable|string|max:20',
            'is_active' => 'sometimes|boolean'
        ]);

        $host->update($validated);
        $host->load(['configuration']);

        return response()->json([
            'message' => 'Host updated successfully',
            'data' => new HostResource($host)
        ]);
    }

    /**
     * @OA\Delete(
     *      path="/api/hosts/{host}",
     *      operationId="deleteHost",
     *      tags={"Hosts"},
     *      summary="Delete host from monitoring (UC21)",
     *      description="Remove host from monitoring system",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Host deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Host deleted successfully")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Remove the specified host from storage (UC21: Usuwanie hosta)
    /// </summary>
    /// <param>Host $host</param>
    /// <returns>JsonResponse</returns>
    public function destroy(Host $host): JsonResponse
    {
        // UC21: Kaskadowe usunięcie wszystkich powiązanych danych (FK CASCADE)
        $host->delete();

        return response()->json([
            'message' => 'Host deleted successfully. All related data has been removed.'
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/hosts/{host}/metrics",
     *      operationId="getHostMetrics",
     *      tags={"Hosts"},
     *      summary="Get host metrics",
     *      description="Get recent metrics for specific host",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="hours",
     *          description="Hours of metrics history",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", default=24)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Host metrics",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Metric"))
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get metrics for specific host (Custom endpoint)
    /// </summary>
    /// <param>Request $request</param>
    /// <param>Host $host</param>
    /// <returns>JsonResponse</returns>
    public function metrics(Request $request, Host $host): JsonResponse
    {
        $hours = $request->get('hours', 24);
        
        $metrics = $host->metrics()
                       ->with('metricType')
                       ->where('timestamp', '>=', now()->subHours($hours))
                       ->orderBy('timestamp', 'desc')
                       ->get();

        return response()->json([
            'host' => [
                'host_id' => $host->host_id,
                'host_name' => $host->host_name,
                'ip_address' => $host->ip_address
            ],
            'period_hours' => $hours,
            'metrics_count' => $metrics->count(),
            'data' => MetricResource::collection($metrics)
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/hosts/{host}/status",
     *      operationId="getHostStatus",
     *      tags={"Hosts"},
     *      summary="Check host connection status (UC23)",
     *      description="Check if host agent is reachable and responsive",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Host status information",
     *          @OA\JsonContent(
     *              @OA\Property(property="host_id", type="integer", example=1),
     *              @OA\Property(property="host_name", type="string", example="web-server-01"),
     *              @OA\Property(property="is_online", type="boolean", example=true),
     *              @OA\Property(property="last_contact", type="string", format="date-time", example="2025-06-22T10:30:00Z"),
     *              @OA\Property(property="response_time_ms", type="integer", example=145),
     *              @OA\Property(property="agent_version", type="string", example="1.0.0")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get connection status for specific host (UC23: Sprawdzanie statusu połączenia)
    /// </summary>
    /// <param>Host $host</param>
    /// <returns>JsonResponse</returns>
    public function status(Host $host): JsonResponse
    {
        $latestStatus = $host->connectionStatuses()
                            ->latest('last_check_date')
                            ->first();

        // UC23: Test połączenia z agentem
        $currentStatus = $this->testAgentConnection($host->ip_address);

        return response()->json([
            'host' => [
                'host_id' => $host->host_id,
                'host_name' => $host->host_name,
                'ip_address' => $host->ip_address
            ],
            'latest_status' => $latestStatus ? [
                'status' => $latestStatus->status,
                'response_time' => $latestStatus->response_time,
                'last_check_date' => $latestStatus->last_check_date->toISOString(),
                'error_message' => $latestStatus->error_message
            ] : null,
            'current_test' => [
                'status' => $currentStatus ? 'Online' : 'Offline',
                'tested_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/hosts/{host}/alerts",
     *      operationId="getHostAlerts",
     *      tags={"Hosts"},
     *      summary="Get host alerts",
     *      description="Get active alerts for specific host",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Host alerts",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Alert"))
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get alerts for specific host
    /// </summary>
    /// <param>Request $request</param>
    /// <param>Host $host</param>
    /// <returns>JsonResponse</returns>
    public function alerts(Request $request, Host $host): JsonResponse
    {
        $query = $host->alerts()->with(['metricType', 'acknowledgedByUser', 'closedByUser']);

        // Filtrowanie według statusu
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtrowanie według poziomu
        if ($request->has('level')) {
            $query->where('alert_level', $request->level);
        }

        $alerts = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'host' => [
                'host_id' => $host->host_id,
                'host_name' => $host->host_name,
                'ip_address' => $host->ip_address
            ],
            'alerts_count' => $alerts->count(),
            'data' => AlertResource::collection($alerts)
        ]);
    }

        /// <summary>
    /// Get total count of hosts for dashboard statistics
    /// </summary>
    /// <returns>JSON response with hosts count</returns>
    public function getHostsCount()
    {
        try {
            $totalHosts = Host::count();
            $activeHosts = Host::where('is_active', true)->count();
            $inactiveHosts = $totalHosts - $activeHosts;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total' => $totalHosts,
                    'active' => $activeHosts,
                    'inactive' => $inactiveHosts
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get hosts count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /// <summary>
    /// Get dashboard statistics for hosts
    /// </summary>
    /// <returns>JSON response with comprehensive host statistics</returns>
    public function getDashboardStats()
    {
        try {
            $stats = [
                'total_hosts' => Host::count(),
                'active_hosts' => Host::where('is_active', true)->count(),
                'hosts_with_alerts' => Host::whereHas('alerts', function($query) {
                    $query->where('is_resolved', false);
                })->count(),
                'hosts_online' => Host::whereHas('connectionStatuses', function($query) {
                    $query->where('is_connected', true)
                        ->where('last_check_date', '>=', now()->subMinutes(5));
                })->count()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Test connection to agent (UC20, UC23)
    /// </summary>
    /// <param>string $ipAddress</param>
    /// <returns>bool</returns>
    private function testAgentConnection(string $ipAddress): bool
    {
        // Symulacja testu połączenia z agentem
        // W produkcji: HTTP request do agenta na porcie np. 8080
        
        if ($ipAddress === '127.0.0.1' || $ipAddress === 'localhost') {
            return true; // Localhost zawsze dostępny dla testów
        }

        // TODO: Implement actual agent ping/HTTP check
        return false;
    }

    #endregion
}