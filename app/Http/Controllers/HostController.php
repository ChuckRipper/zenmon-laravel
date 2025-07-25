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
use Illuminate\Support\Facades\Log;

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
    /// <returns>JsonResponse</returns>
    public function index(Request $request): JsonResponse
    {
        // $query = Host::with(['configuration', 'connectionStatuses' => function($query) {
        //     $query->latest('last_check_date')->limit(1);
        // }]);

        $query = Host::with(['configuration.updatedByUser', 'connectionStatuses' => function($query) {
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

        $perPage = $request->input('per_page', 15);
        $hosts = $query->paginate($perPage);
        // return new HostCollection($hosts);
        return (new HostCollection($hosts))->response();
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

        // XSS Protection - sanitize input data
        $validated['host_name'] = strip_tags($validated['host_name']);
        if (isset($validated['description'])) {
            $validated['description'] = strip_tags($validated['description']);
        }
        if (isset($validated['operating_system'])) {
            $validated['operating_system'] = strip_tags($validated['operating_system']);
        }

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
     * @OA\Get(
     *      path="/hosts/{host}/edit",
     *      operationId="editHost",
     *      tags={"Hosts"},
     *      summary="Show edit form for host (UC21)",
     *      description="Returns edit form for existing host - Web UI only",
     *      @OA\Parameter(
     *          name="host",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Edit form view",
     *          @OA\MediaType(
     *              mediaType="text/html",
     *              @OA\Schema(type="string")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Host not found"),
     *      @OA\Response(response=403, description="Access denied - Administrator role required")
     * )
     */
    /// <summary>
    /// Show the form for editing the specified host (UC21: Edycja hosta)
    /// </summary>
    /// <param>Host $host</param>
    /// <returns>View</returns>
    public function edit(Host $host)
    {
        // UC21: Tylko administratorzy mogą edytować hosty
        if (!auth()->user() || auth()->user()->role !== 'Administrator') {
            abort(403, 'Administrator access required');
        }

        $host->load(['configuration', 'connectionStatuses' => function($query) {
            $query->latest('last_check_date')->limit(1);
        }]);

        // Sprawdź ostatni status połączenia
        $latestConnection = $host->connectionStatuses->first();
        $connectionStatus = $latestConnection ? $latestConnection->status : 'Unknown';
        $lastCheck = $latestConnection ? $latestConnection->last_check_date : null;

        Log::info('Host edit form accessed', [
            'host_id' => $host->host_id,
            'host_name' => $host->host_name,
            'accessed_by' => auth()->user()->login ?? 'unknown',
            'connection_status' => $connectionStatus
        ]);

        return view('hosts.edit', compact('host', 'connectionStatus', 'lastCheck'));
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
    public function destroy(int $host_id): JsonResponse
    {
        $host = Host::find($host_id);
        if (!$host) {
            return response()->json(['message' => 'Host not found'], 404);
        }
        
        try {
            // UC21: Ręczne usunięcie powiązanych danych przed usunięciem hosta
            
            // Usuń metryki
            $host->metrics()->delete();
            
            // Usuń alerty
            $host->alerts()->delete();
            
            // Usuń statusy połączeń
            $host->connectionStatuses()->delete();
            
            // Usuń monitorowane katalogi i ich metryki
            $monitoredDirectories = $host->monitoredDirectories;
            foreach ($monitoredDirectories as $directory) {
                $directory->directoryMetrics()->delete();
            }
            $host->monitoredDirectories()->delete();
            
            // Usuń progi alertów
            $host->alertThresholds()->delete();
            
            // Usuń konfigurację
            if ($host->configuration) {
                $host->configuration->delete();
            }
            
            // Usuń host
            $host->delete();

            return response()->json([
                'message' => 'Host deleted successfully. All related data has been removed.'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to delete host', [
                'host_id' => $host->host_id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Failed to delete host: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/hosts/{host}/metrics",
     *      operationId="getHostMetrics",
     *      tags={"Hosts"},
     *      summary="Get metrics for specific host",
     *      description="Returns paginated list of metrics for the specified host",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="metric_type_id",
     *          description="Filter by metric type",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="hours",
     *          description="Get metrics from last N hours",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", maximum=8760)
     *      ),
     *      @OA\Parameter(
     *          name="limit",
     *          description="Number of metrics to return",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", maximum=1000)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Host metrics",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Metric")),
     *              @OA\Property(property="host_info", type="object"),
     *              @OA\Property(property="summary", type="object")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Get metrics for specific host
    /// </summary>
    /// <param>Request $request</param>
    /// <param>Host $host</param>
    /// <returns>JsonResponse</returns>
    public function getMetrics(Request $request, Host $host): JsonResponse
    {
        $query = $host->metrics()->with(['metricType']);

        // Filter by metric type if specified
        if ($request->filled('metric_type_id')) {
            $query->where('metric_type_id', $request->metric_type_id);
        }

        // Time range filtering
        if ($request->filled('hours')) {
            $hours = min($request->hours, 8760); // Max 1 year
            $query->where('timestamp', '>=', now()->subHours($hours));
        }

        // Limit results
        $limit = min($request->get('limit', 100), 1000);
        $metrics = $query->latest('timestamp')->limit($limit)->get();

        return response()->json([
            'data' => \App\Http\Resources\MetricResource::collection($metrics),
            'host_info' => [
                'host_id' => $host->host_id,
                'host_name' => $host->host_name,
                'ip_address' => $host->ip_address,
                'operating_system' => $host->operating_system,
                'is_active' => $host->is_active,
                'last_contact_date' => $host->last_contact_date
            ],
            'summary' => [
                'total_metrics' => $metrics->count(),
                'metric_types_count' => $metrics->pluck('metric_type_id')->unique()->count(),
                'latest_timestamp' => $metrics->first()?->timestamp,
                'oldest_timestamp' => $metrics->last()?->timestamp
            ]
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/hosts/{host}/status", 
     *      operationId="getHostStatus",
     *      tags={"Hosts"},
     *      summary="Get host status information",
     *      description="Returns current status and health information for the specified host",
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
     *              type="object",
     *              @OA\Property(property="host_id", type="integer"),
     *              @OA\Property(property="status", type="string"),
     *              @OA\Property(property="connection_status", type="object"),
     *              @OA\Property(property="latest_metrics", type="object"),
     *              @OA\Property(property="alerts_summary", type="object"),
     *              @OA\Property(property="health_score", type="number")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Get comprehensive status information for host
    /// </summary>
    /// <param>Request $request</param>
    /// <param>Host $host</param>
    /// <returns>JsonResponse</returns>
    public function getHostStatus(Request $request, Host $host): JsonResponse
    {
        // Get latest connection status
        $latestConnection = $host->connectionStatuses()
            ->latest('last_check_date')
            ->first();

        // Get latest metrics (last 24 hours)
        $latestMetrics = $host->metrics()
            ->with(['metricType'])
            ->where('timestamp', '>=', now()->subHours(24))
            ->latest('timestamp')
            ->limit(10)
            ->get();

        // Get alerts summary
        $activeAlerts = $host->alerts()
            ->where('status', 'Active')
            ->count();

        $resolvedAlerts = $host->alerts()
            ->where('status', 'Resolved')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Calculate basic health score
        $healthScore = $this->calculateHealthScore($host, $latestConnection, $latestMetrics, $activeAlerts);

        return response()->json([
            'host_id' => $host->host_id,
            'host_name' => $host->host_name,
            'status' => $host->is_active ? 'active' : 'inactive',
            'connection_status' => [
                'status' => $latestConnection?->status ?? 'Unknown',
                'last_check' => $latestConnection?->last_check_date,
                'response_time' => $latestConnection?->response_time,
                'error_message' => $latestConnection?->error_message
            ],
            'latest_metrics' => [
                'count' => $latestMetrics->count(),
                'latest_timestamp' => $latestMetrics->first()?->timestamp,
                'metric_types' => $latestMetrics->pluck('metricType.metric_name')->unique()->values()
            ],
            'alerts_summary' => [
                'active_alerts' => $activeAlerts,
                'resolved_last_7d' => $resolvedAlerts,
                'alert_level' => $activeAlerts > 0 ? 'warning' : 'normal'
            ],
            'health_score' => $healthScore,
            'last_contact_date' => $host->last_contact_date,
            'agent_version' => $host->agent_version,
            'operating_system' => $host->operating_system
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/hosts/{host}/alerts",
     *      operationId="getHostAlerts",
     *      tags={"Hosts"},
     *      summary="Get alerts for specific host",
     *      description="Returns list of alerts for the specified host with filtering options",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          description="Filter by alert status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", enum={"Active", "Acknowledged", "Resolved"})
     *      ),
     *      @OA\Parameter(
     *          name="level",
     *          description="Filter by alert level",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string", enum={"Warning", "Critical"})
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Host alerts",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Alert")),
     *              @OA\Property(property="host_info", type="object"),
     *              @OA\Property(property="summary", type="object")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Get alerts for specific host
    /// </summary>
    /// <param>Request $request</param>
    /// <param>Host $host</param>
    /// <returns>JsonResponse</returns>
    public function getAlerts(Request $request, Host $host): JsonResponse
    {
        $query = $host->alerts()->with(['metricType', 'acknowledgedByUser', 'closedByUser']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by level
        if ($request->filled('level')) {
            $query->where('alert_level', $request->level);
        }

        // Get timeframe
        if ($request->filled('hours')) {
            $hours = min($request->hours, 8760); // Max 1 year
            $query->where('created_at', '>=', now()->subHours($hours));
        }

        $alerts = $query->orderBy('created_at', 'desc')->get();

        // Calculate summary
        $summary = [
            'total_alerts' => $alerts->count(),
            'active_alerts' => $alerts->where('status', 'Active')->count(),
            'warning_alerts' => $alerts->where('alert_level', 'Warning')->count(),
            'critical_alerts' => $alerts->where('alert_level', 'Critical')->count(),
            'acknowledged_alerts' => $alerts->where('status', 'Acknowledged')->count(),
            'resolved_alerts' => $alerts->where('status', 'Resolved')->count()
        ];

        return response()->json([
            'data' => \App\Http\Resources\AlertResource::collection($alerts),
            'host_info' => [
                'host_id' => $host->host_id,
                'host_name' => $host->host_name,
                'ip_address' => $host->ip_address,
                'operating_system' => $host->operating_system,
                'is_active' => $host->is_active
            ],
            'summary' => $summary
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/agent/status/{hostId}",
     *      operationId="updateAgentStatus",
     *      tags={"Agent"},
     *      summary="Update agent status",
     *      description="Update agent status and connection information from agent",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="hostId",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="status", type="string", enum={"online", "offline", "error"}),
     *              @OA\Property(property="agent_version", type="string"),
     *              @OA\Property(property="error_message", type="string", nullable=true),
     *              @OA\Property(property="system_info", type="object", nullable=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Agent status updated",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="host_id", type="integer"),
     *              @OA\Property(property="status_updated", type="boolean")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Update agent status from agent itself
    /// </summary>
    /// <param>Request $request</param>
    /// <param>int $hostId</param>
    /// <returns>JsonResponse</returns>
    public function updateAgentStatus(Request $request, int $hostId): JsonResponse
    {
        $host = Host::findOrFail($hostId);

        $validated = $request->validate([
            'status' => 'required|string|in:online,offline,error',
            'agent_version' => 'sometimes|string|max:50',
            'error_message' => 'nullable|string|max:500',
            'system_info' => 'sometimes|array'
        ]);

        // Update host information
        $updateData = [
            'last_contact_date' => now(),
            'is_active' => $validated['status'] === 'online'
        ];

        if (isset($validated['agent_version'])) {
            $updateData['agent_version'] = $validated['agent_version'];
        }

        $host->update($updateData);

        // Create connection status record
        $connectionData = [
            'host_id' => $hostId,
            'status' => ucfirst($validated['status'] === 'online' ? 'Online' : 'Offline'),
            'last_check_date' => now(),
            'error_message' => $validated['error_message'] ?? null,
            'response_time' => null // Agent self-reporting doesn't have response time
        ];

        \App\Models\ConnectionStatus::create($connectionData);

        Log::info("Agent status updated for host {$hostId}", [
            'status' => $validated['status'],
            'agent_version' => $validated['agent_version'] ?? 'unknown',
            'error_message' => $validated['error_message'] ?? null
        ]);

        return response()->json([
            'message' => 'Agent status updated successfully',
            'host_id' => $hostId,
            'host_name' => $host->host_name,
            'status_updated' => true,
            'current_status' => $validated['status'],
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * @OA\Get(
     *      path="/api/public/hosts/count",
     *      operationId="getPublicHostCount",
     *      tags={"Public"},
     *      summary="Get public host statistics",
     *      description="Returns basic host count statistics - no authentication required",
     *      @OA\Response(
     *          response=200,
     *          description="Host count statistics",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="total_hosts", type="integer"),
     *              @OA\Property(property="active_hosts", type="integer"),
     *              @OA\Property(property="timestamp", type="string")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get public host count statistics
    /// </summary>
    /// <returns>JsonResponse</returns>
    public function getPublicHostCount(): JsonResponse
    {
        $totalHosts = Host::count();
        $activeHosts = Host::where('is_active', true)->count();
        $onlineHosts = Host::whereHas('connectionStatuses', function ($query) {
            $query->where('status', 'Online')
                  ->where('last_check_date', '>=', now()->subHours(1));
        })->count();

        return response()->json([
            'total_hosts' => $totalHosts,
            'active_hosts' => $activeHosts,
            'online_hosts' => $onlineHosts,
            'offline_hosts' => $totalHosts - $onlineHosts,
            'activity_rate' => $totalHosts > 0 ? round(($activeHosts / $totalHosts) * 100, 1) : 0,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/hosts/{host}/configuration",
     *      operationId="hostUpdateConfiguration",
     *      tags={"Hosts"},
     *      summary="Update host monitoring configuration (UC24)",
     *      description="Update monitoring parameters for a specific host - Administrator only",
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
     *          description="Host configuration parameters",
     *          @OA\JsonContent(
     *              @OA\Property(property="data_collection_interval", type="integer", minimum=30, maximum=600, example=120, description="Interval in seconds"),
     *              @OA\Property(property="enable_cpu_monitoring", type="boolean", example=true),
     *              @OA\Property(property="enable_ram_monitoring", type="boolean", example=true),
     *              @OA\Property(property="enable_disk_monitoring", type="boolean", example=true),
     *              @OA\Property(property="enable_network_monitoring", type="boolean", example=false)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Configuration updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Host configuration updated successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/HostConfiguration"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Host not found"
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error"
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Administrator access required"
     *      )
     * )
     */
    /// <summary>
    /// Update host configuration (UC24)
    /// </summary>
    /// <param>Request $request</param>
    /// <param>Host $host</param>
    /// <returns>JsonResponse</returns>
    public function updateConfiguration(Request $request, int $host_id): JsonResponse
    {
        try {
            // Znajdź host po ID
            $host = Host::find($host_id);
            if (!$host) {
                return response()->json(['message' => 'Host not found'], 404);
            }

            // Debug: Sprawdź załadowanego hosta
            Log::info('updateConfiguration debug', [
                'host_id_param' => $host_id,
                'host_found' => $host ? 'YES' : 'NO',
                'host_id' => $host ? $host->host_id : 'NULL',
                'host_attributes' => $host ? $host->getAttributes() : 'NULL'
            ]);

            $validated = $request->validate([
                'data_collection_interval' => 'sometimes|integer|min:30|max:600',
                'enable_cpu_monitoring' => 'sometimes|boolean',
                'enable_ram_monitoring' => 'sometimes|boolean',
                'enable_disk_monitoring' => 'sometimes|boolean',
                'enable_network_monitoring' => 'sometimes|boolean'
            ]);

            // Update or create host configuration
            $configuration = $host->configuration;
            
            // Dodaj użytkownika który aktualizuje
            $validated['updated_by_user_id'] = auth()->id();

            if ($configuration) {
                $configuration->update($validated);
            } else {
                $validated['host_id'] = $host->host_id;
                
                // Dodaj domyślne wartości jeśli nie zostały podane
                $validated['data_collection_interval'] = $validated['data_collection_interval'] ?? 120;
                $validated['enable_cpu_monitoring'] = $validated['enable_cpu_monitoring'] ?? true;
                $validated['enable_ram_monitoring'] = $validated['enable_ram_monitoring'] ?? true;
                $validated['enable_disk_monitoring'] = $validated['enable_disk_monitoring'] ?? true;
                $validated['enable_network_monitoring'] = $validated['enable_network_monitoring'] ?? true;
                
                $configuration = HostConfiguration::create($validated);
            }

            // Załaduj relacje dla prawidłowej odpowiedzi
            $configuration->load(['host', 'updatedByUser']);

            Log::info('Host configuration updated', [
                'host_id' => $host->host_id,
                'updated_by' => auth()->user()->login ?? 'system',
                'changes' => $validated
            ]);

            return response()->json([
                'message' => 'Host configuration updated successfully',
                'data' => new \App\Http\Resources\HostConfigurationResource($configuration)
            ]);

        } catch (\Exception $e) {
            Log::error('HostController@updateConfiguration failed', [
                'host_id' => $host_id,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'Failed to update host configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #endregion

    #region Private Helper Methods

    /// <summary>
    /// Calculate health score for host (0-100)
    /// </summary>
    /// <param>Host $host</param>
    /// <param>$latestConnection</param>
    /// <param>$latestMetrics</param>
    /// <param>int $activeAlerts</param>
    /// <returns>float</returns>
    private function calculateHealthScore(Host $host, $latestConnection, $latestMetrics, int $activeAlerts): float
    {
        $score = 100.0;

        // Deduct points for inactive host
        if (!$host->is_active) {
            $score -= 50;
        }

        // Deduct points for connection issues
        if ($latestConnection) {
            if ($latestConnection->status === 'Offline') {
                $score -= 30;
            } elseif ($latestConnection->status === 'Unknown') {
                $score -= 20;
            }
            
            // Response time penalty
            if ($latestConnection->response_time > 5000) { // > 5 seconds
                $score -= 10;
            }
        } else {
            $score -= 25; // No connection data
        }

        // Deduct points for lack of recent metrics
        if ($latestMetrics->isEmpty()) {
            $score -= 20;
        } elseif ($latestMetrics->first()->timestamp < now()->subHours(2)) {
            $score -= 10; // Stale data
        }

        // Deduct points for active alerts
        $score -= min($activeAlerts * 5, 25); // Max 25 points for alerts

        return max(0, round($score, 1));
    }

    /// <summary>
    /// Test connection to agent (UC20, UC23)
    /// </summary>
    /// <param>string $ipAddress</param>
    /// <returns>bool</returns>
    private function testAgentConnection(string $ipAddress): bool
    {
        // Localhost zawsze dostępny dla testów developera
        if ($ipAddress === '127.0.0.1' || $ipAddress === 'localhost') {
            return true;
        }

        try {
            // Standardowy port agenta ZenMon
            $agentPort = env('ZENMON_AGENT_PORT', 8080);
            $timeout = env('ZENMON_AGENT_TIMEOUT', 5);
            
            $url = "http://{$ipAddress}:{$agentPort}/health";
            
            // Próba połączenia z agentem
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'header' => [
                        'User-Agent: ZenMon-WebApp/1.0',
                        'Accept: application/json'
                    ]
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            
            // Sprawdź czy otrzymaliśmy odpowiedź
            if ($response !== false && isset($http_response_header)) {
                // Parsuj pierwszy nagłówek HTTP
                $statusLine = $http_response_header[0];
                preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches);
                $statusCode = isset($matches[1]) ? (int)$matches[1] : 0;
                
                // Agent jest dostępny jeśli zwrócił kod 200 lub 204
                return in_array($statusCode, [200, 204]);
            }
            
            return false;
            
        } catch (\Exception $e) {
            // Loguj błąd dla debugowania
            \Log::warning("Agent connection test failed for {$ipAddress}: " . $e->getMessage());
            return false;
        }
    }

    #endregion
}