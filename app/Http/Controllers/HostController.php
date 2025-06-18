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

class HostController extends Controller
{
    #region Methods
    
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
