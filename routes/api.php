<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\{
    HostController,
    AlertController,
    MetricController,
    MetricTypeController,
    HostConfigurationController,
    AlertThresholdController,
    ConnectionStatusController,
    MonitoredDirectoryController,
    DirectoryMetricController,
    UserSessionController
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Test endpoint for database connection
Route::get('/test', function () {
    try {
        \DB::connection()->getPdo();
        return response()->json([
            'message' => 'Database connection successful',
            'timestamp' => now()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Database connection failed',
            'error' => $e->getMessage()
        ], 500);
    }
});

// Authentication endpoint for testing
// Route::post('/login', function (Request $request) {
//     $request->validate([
//         'email' => 'required|email',
//         'password' => 'required'
//     ]);

//     // Simple test user for development
//     if ($request->email === 'admin@zenmon.local' && $request->password === 'password') {
//         return response()->json([
//             'message' => 'Authentication successful',
//             'token' => 'test-token-' . \Str::random(40),
//             'user' => [
//                 'id' => 1,
//                 'email' => 'admin@zenmon.local',
//                 'name' => 'ZenMon Admin'
//             ]
//         ]);
//     }

//     return response()->json(['message' => 'Invalid credentials'], 401);
// });

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'login' => 'required|string',
        'password' => 'required|string',
    ]);

    $user = \App\Models\User::where('login', $credentials['login'])->first();

    if ($user && Hash::check($credentials['password'], $user->password)) {
        $token = $user->createToken('zenmon-api-token')->plainTextToken;
        
        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'login' => $user->login,
                'full_name' => $user->first_name . ' ' . $user->last_name,
                'role' => $user->role
            ]
        ]);
    }

    return response()->json(['message' => 'Invalid credentials'], 401);
});

/*
|--------------------------------------------------------------------------
| Protected API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    
    // Host Management (UC20-24)
    Route::apiResource('hosts', HostController::class);
    Route::get('/hosts/{host}/metrics', [HostController::class, 'getMetrics']);
    Route::get('/hosts/{host}/alerts', [HostController::class, 'getAlerts']);
    Route::post('/hosts/{host}/configuration', [HostController::class, 'updateConfiguration']);
    Route::get('/hosts/search/network', [HostController::class, 'searchInNetwork']);
    
    // Host Configuration (UC24)
    Route::apiResource('host-configurations', HostConfigurationController::class);
    Route::post('/host-configurations/bulk', [HostConfigurationController::class, 'bulkUpdate']);
    
    // Connection Status (UC23)
    Route::apiResource('connection-status', ConnectionStatusController::class);
    Route::post('/connection-status/check', [ConnectionStatusController::class, 'checkConnection']);
    
    // Monitored Directories Management - DODANY ROUTING!
    Route::apiResource('monitored-directories', MonitoredDirectoryController::class);
    Route::get('/monitored-directories/host/{host}', [MonitoredDirectoryController::class, 'getByHost']);
    Route::post('/monitored-directories/bulk', [MonitoredDirectoryController::class, 'bulkCreate']);
    
    // Directory Metrics
    Route::apiResource('directory-metrics', DirectoryMetricController::class);
    Route::get('/directory-metrics/directory/{directory}', [DirectoryMetricController::class, 'getByDirectory']);
    Route::post('/directory-metrics/batch', [DirectoryMetricController::class, 'batchStore']);
    
    // Metrics (UC30-33)
    Route::apiResource('metrics', MetricController::class);
    Route::post('/metrics/batch', [MetricController::class, 'batchStore']);
    Route::get('/metrics/latest/{host}', [MetricController::class, 'getLatestForHost']);
    Route::get('/metrics/historical', [MetricController::class, 'getHistoricalData']);
    Route::delete('/metrics/cleanup', [MetricController::class, 'cleanupOldMetrics']);
    
    // Metric Types
    Route::apiResource('metric-types', MetricTypeController::class);
    Route::get('/metric-types/stats', [MetricTypeController::class, 'getStatsWithCounts']);
    Route::get('/metric-types/units', [MetricTypeController::class, 'getAvailableUnits']);
    
    // Alerts (UC41-43, UC44: API access)
    Route::apiResource('alerts', AlertController::class);
    Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge']);
    Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve']);
    Route::get('/alerts/active', [AlertController::class, 'getActiveAlerts']);
    
    // Alert Thresholds (UC40)
    Route::apiResource('alert-thresholds', AlertThresholdController::class);
    Route::get('/alert-thresholds/metric-type/{metricType}', [AlertThresholdController::class, 'getByMetricType']);
    Route::post('/alert-thresholds/bulk', [AlertThresholdController::class, 'bulkUpdate']);
    
    // User Sessions Management
    Route::apiResource('user-sessions', UserSessionController::class);
    Route::get('/user-sessions/active', [UserSessionController::class, 'getActiveSessions']);
    Route::post('/user-sessions/{session}/terminate', [UserSessionController::class, 'terminateSession']);
    
});

/*
|--------------------------------------------------------------------------
| Agent API Routes (Unauthenticated for Agent Communication)
|--------------------------------------------------------------------------
*/

// Agent endpoints - without authentication for easier agent integration
Route::prefix('agent')->group(function () {
    
    // Agent registration
    Route::post('/register', [HostController::class, 'registerAgent']);
    
    // Metric submission from agents
    Route::post('/metrics', [MetricController::class, 'receiveFromAgent']);
    Route::post('/metrics/batch', [MetricController::class, 'batchReceiveFromAgent']);
    
    // Directory metrics from agents
    Route::post('/directory-metrics', [DirectoryMetricController::class, 'receiveFromAgent']);
    
    // Agent heartbeat
    Route::post('/heartbeat/{hostId}', [ConnectionStatusController::class, 'receiveHeartbeat']);
    
    // Agent status update
    Route::post('/status/{hostId}', [HostController::class, 'updateAgentStatus']);
    
});

/*
|--------------------------------------------------------------------------
| Public API Routes (Read-only endpoints for dashboards)
|--------------------------------------------------------------------------
*/

Route::prefix('public')->group(function () {
    
    // Health check endpoint - DODANE!
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'service' => 'ZenMon API'
        ]);
    });
    
    // Public dashboard endpoints (if needed)
    Route::get('/hosts/count', [HostController::class, 'getHostsCount']);
    Route::get('/alerts/summary', [AlertController::class, 'getAlertsSummary']);
    Route::get('/metrics/summary', [MetricController::class, 'getMetricsSummary']);
    
});

/*
|--------------------------------------------------------------------------
| API Documentation
|--------------------------------------------------------------------------
|
| Hosts API (UC20-24):
| GET    /api/hosts                      - List hosts (with filters)
| POST   /api/hosts                      - Add new host
| GET    /api/hosts/{id}                 - Show host details
| PUT    /api/hosts/{id}                 - Update host
| DELETE /api/hosts/{id}                 - Delete host
| GET    /api/hosts/{id}/metrics         - Get host metrics
| GET    /api/hosts/{id}/alerts          - Get host alerts
| POST   /api/hosts/{id}/configuration   - Update host configuration
| GET    /api/hosts/search/network       - Network scan for agents
|
| Host Configurations API (UC24):
| GET    /api/host-configurations        - List configurations
| POST   /api/host-configurations        - Create configuration
| GET    /api/host-configurations/{id}   - Show configuration
| PUT    /api/host-configurations/{id}   - Update configuration
| DELETE /api/host-configurations/{id}   - Delete configuration
| POST   /api/host-configurations/bulk   - Bulk update configurations
|
| Connection Status API (UC23):
| GET    /api/connection-status          - List connection statuses
| POST   /api/connection-status/check    - Check connection status
|
| Monitored Directories API:
| GET    /api/monitored-directories              - List monitored directories
| POST   /api/monitored-directories              - Add directory to monitoring
| GET    /api/monitored-directories/{id}         - Show directory details
| PUT    /api/monitored-directories/{id}         - Update directory
| DELETE /api/monitored-directories/{id}         - Remove from monitoring
| GET    /api/monitored-directories/host/{host}  - Get directories for host
| POST   /api/monitored-directories/bulk         - Bulk add directories
|
| Directory Metrics API:
| GET    /api/directory-metrics                        - List directory metrics
| POST   /api/directory-metrics                        - Store directory metric
| GET    /api/directory-metrics/{id}                   - Show directory metric
| PUT    /api/directory-metrics/{id}                   - Update directory metric
| DELETE /api/directory-metrics/{id}                  - Delete directory metric
| GET    /api/directory-metrics/directory/{directory} - Get metrics for directory
| POST   /api/directory-metrics/batch                 - Batch store metrics
|
| Metrics API (UC30-33):
| GET    /api/metrics                    - List metrics (filtered)
| POST   /api/metrics                    - Store metric
| GET    /api/metrics/{id}               - Show metric
| PUT    /api/metrics/{id}               - Update metric
| DELETE /api/metrics/{id}               - Delete metric
| POST   /api/metrics/batch              - Store multiple metrics
| GET    /api/metrics/latest/{hostId}    - Latest metrics for host
| GET    /api/metrics/historical         - Historical data for charts
| DELETE /api/metrics/cleanup            - Cleanup old metrics
|
| Metric Types API:
| GET    /api/metric-types               - List metric types
| POST   /api/metric-types               - Create metric type
| GET    /api/metric-types/{id}          - Show metric type
| PUT    /api/metric-types/{id}          - Update metric type
| DELETE /api/metric-types/{id}          - Delete metric type
| GET    /api/metric-types/stats         - Metric types with statistics
| GET    /api/metric-types/units         - Available units
|
| Alerts API (UC41-43, UC44: API access):
| GET    /api/alerts                     - List alerts
| POST   /api/alerts                     - Create alert
| GET    /api/alerts/{id}                - Show alert
| PUT    /api/alerts/{id}                - Update alert
| DELETE /api/alerts/{id}                - Delete alert
| POST   /api/alerts/{id}/acknowledge    - Acknowledge alert
| POST   /api/alerts/{id}/resolve        - Resolve alert
| GET    /api/alerts/active              - Get active alerts
|
| Alert Thresholds API (UC40):
| GET    /api/alert-thresholds                       - List alert thresholds
| POST   /api/alert-thresholds                       - Create threshold
| GET    /api/alert-thresholds/{id}                  - Show threshold
| PUT    /api/alert-thresholds/{id}                  - Update threshold
| DELETE /api/alert-thresholds/{id}                  - Delete threshold
| GET    /api/alert-thresholds/metric-type/{type}   - Thresholds for metric type
| POST   /api/alert-thresholds/bulk                  - Bulk update thresholds
|
| User Sessions API:
| GET    /api/user-sessions                    - List user sessions
| POST   /api/user-sessions/{id}/terminate     - Terminate session
| GET    /api/user-sessions/active             - Get active sessions
|
| Agent API (for agent communication):
| POST   /api/agent/register                   - Register new agent
| POST   /api/agent/metrics                    - Submit metrics from agent
| POST   /api/agent/metrics/batch              - Submit batch metrics
| POST   /api/agent/directory-metrics          - Submit directory metrics
| POST   /api/agent/heartbeat/{hostId}         - Agent heartbeat
| POST   /api/agent/status/{hostId}            - Update agent status
|
| Public API (no auth required):
| GET    /api/public/health                    - Health check endpoint
| GET    /api/public/hosts/count               - Get hosts count
| GET    /api/public/alerts/summary            - Get alerts summary
| GET    /api/public/metrics/summary           - Get metrics summary
|
*/