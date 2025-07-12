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
| API Routes - ZenMon Secure Implementation
|--------------------------------------------------------------------------
|
| SECURITY MODEL:
| - UNAUTHENTICATED: /login, /public/*
| - AUTHENTICATED: Everything else requires Bearer token
|
*/

/*
|--------------------------------------------------------------------------
| UNAUTHENTICATED ROUTES (Public Access)
|--------------------------------------------------------------------------
*/

// Authentication endpoint
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
            ],
            'expires_at' => null
        ]);
    }

    return response()->json([
        'message' => 'Invalid credentials'
    ], 401);
});

// Public health endpoints
Route::prefix('public')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'ZenMon API',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString()
        ]);
    });

    Route::get('/hosts/count', [HostController::class, 'getPublicHostCount']);
    Route::get('/alerts/summary', [AlertController::class, 'getPublicAlertSummary']);
    Route::get('/metrics/summary', [MetricController::class, 'getPublicMetricsSummary']);
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (Bearer Token Required)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Test endpoints
    Route::prefix('test')->group(function () {
        Route::get('/database', function () {
            try {
                \DB::connection()->getPdo();
                return response()->json(['status' => 'connected', 'database' => 'OK']);
            } catch (\Exception $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        });

        Route::get('/auth', function (Request $request) {
            return response()->json([
                'authenticated' => true,
                'user' => $request->user()->only(['id', 'login', 'role']),
                'token_name' => $request->user()->currentAccessToken()->name,
                'timestamp' => now()->toISOString()
            ]);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | AGENT ENDPOINTS (UC31)
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('agent')->group(function () {
        
        // Single metric submission from agents
        Route::post('/metrics', [MetricController::class, 'receiveFromAgent']);

        // Batch metrics submission from agents
        Route::post('/metrics/batch', [MetricController::class, 'batchReceiveFromAgent']);
        
        // Directory metrics from agents
        Route::post('/directory-metrics', [DirectoryMetricController::class, 'receiveFromAgent']);
        
        // Agent heartbeat
        Route::post('/heartbeat/{hostId}', [ConnectionStatusController::class, 'receiveHeartbeat']);
        
        // Agent status update
        Route::post('/status/{hostId}', [HostController::class, 'updateAgentStatus']);

        // Agent configuration endpoints
        Route::get('/configuration/{hostId}', [HostConfigurationController::class, 'getAgentConfiguration']);
        Route::get('/monitored-directories/{hostId}', [MonitoredDirectoryController::class, 'getAgentDirectories']);
        
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN & USER API ENDPOINTS (Secured with Bearer Token)
    |--------------------------------------------------------------------------
    */
    
    // Host Management (UC20-24)
    Route::get('/hosts/{host}/metrics', [HostController::class, 'getMetrics']);
    Route::get('/hosts/{host}/alerts', [HostController::class, 'getAlerts']);
    Route::get('/hosts/{host}/status', [HostController::class, 'getHostStatus']); // ADDED: Missing route
    Route::post('/hosts/{host}/configuration', [HostController::class, 'updateConfiguration']);
    Route::get('/hosts/search/network', [HostController::class, 'searchInNetwork']);

    Route::apiResource('hosts', HostController::class);
    
    // Host Configuration (UC24)
    Route::post('/host-configurations/bulk', [HostConfigurationController::class, 'bulkUpdate']);

    // Route::apiResource('host-configurations', HostConfigurationController::class);
    Route::apiResource('host-configurations', HostConfigurationController::class, [
        'parameters' => ['host-configurations' => 'configuration']
    ]);
    
    // Connection Status (UC23) - FIXED: Added both connection-status and connection-statuses
    Route::get('/connection-statuses/latest', [ConnectionStatusController::class, 'getLatestStatuses']); // ADDED: Missing route
    Route::post('/connection-status/check', [ConnectionStatusController::class, 'checkConnection']);
    Route::get('/connection-statuses/host/{hostId}/statistics', [ConnectionStatusController::class, 'getHostStatistics']);

    Route::apiResource('connection-status', ConnectionStatusController::class);
    Route::apiResource('connection-statuses', ConnectionStatusController::class); // ADDED: Alternative route
    
    // Monitored Directories Management
    Route::get('/monitored-directories/host/{host}', [MonitoredDirectoryController::class, 'getByHost']);
    Route::post('/monitored-directories/bulk', [MonitoredDirectoryController::class, 'bulkCreate']);

    Route::apiResource('monitored-directories', MonitoredDirectoryController::class);
    
    // Directory Metrics
    // Route::apiResource('directory-metrics', DirectoryMetricController::class);
    Route::get('/directory-metrics/directory/{directory}', [DirectoryMetricController::class, 'getByDirectory']);
    Route::post('/directory-metrics/batch', [DirectoryMetricController::class, 'batchStore']);

    Route::apiResource('directory-metrics', DirectoryMetricController::class, [
        'parameters' => ['directory-metrics' => 'directoryMetric']
    ]);
    
    // Metrics (UC30-33)
    Route::post('/metrics/batch', [MetricController::class, 'batchStore']);
    Route::get('/metrics/latest/{host}', [MetricController::class, 'getLatestByHost']);
    Route::get('/metrics/historical', [MetricController::class, 'getHistorical']);
    Route::delete('/metrics/cleanup', [MetricController::class, 'cleanup']);

    Route::apiResource('metrics', MetricController::class);
    
    // Metric Types (UC28, UC29)
    Route::get('/metric-types/stats', [MetricTypeController::class, 'getWithStats']); // FIXED: Added specific route for stats
    Route::get('/metric-types/units', [MetricTypeController::class, 'getAvailableUnits']);
    Route::get('/metric-types/usage/statistics', [MetricTypeController::class, 'getUsageStatistics']);

    Route::apiResource('metric-types', MetricTypeController::class);
    
    // Alert Management (UC34-37)
    Route::post('/alerts/acknowledge/{alert}', [AlertController::class, 'acknowledge']);
    Route::post('/alerts/resolve/{alert}', [AlertController::class, 'resolve']);
    Route::get('/alerts/dashboard', [AlertController::class, 'getDashboardData']);

    Route::apiResource('alerts', AlertController::class);
    
    // Alert Thresholds (UC35)
    Route::post('/alert-thresholds/bulk', [AlertThresholdController::class, 'bulkCreate']);
    Route::get('/alert-thresholds/host/{host}', [AlertThresholdController::class, 'getByHost']);

    Route::apiResource('alert-thresholds', AlertThresholdController::class);
    
    // User Sessions (UC38-41)
    Route::post('/user-sessions/cleanup', [UserSessionController::class, 'cleanup']);
    Route::get('/user-sessions/active', [UserSessionController::class, 'getActiveSessions']);

    Route::apiResource('user-sessions', UserSessionController::class);

    // User token management
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    });

    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'token_name' => $request->user()->currentAccessToken()->name,
            'timestamp' => now()->toISOString()
        ]);
    });

});

/*
|--------------------------------------------------------------------------
| API Routes Documentation
|--------------------------------------------------------------------------
|
| SECURITY SUMMARY:
|
| UNAUTHENTICATED (Public):
| POST   /api/login                             - Obtain Bearer token
| GET    /api/public/health                     - Health check
| GET    /api/public/hosts/count               - Public host statistics  
| GET    /api/public/alerts/summary            - Public alert statistics
| GET    /api/public/metrics/summary           - Public metrics statistics
|
| AUTHENTICATED (Bearer Token Required):
| ALL OTHER ENDPOINTS require Authorization: Bearer {token}
|
| Agent Endpoints (UC31):
| POST   /api/agent/metrics/batch              - Submit metrics (SECURED)
| POST   /api/agent/directory-metrics          - Submit directory metrics (SECURED)
| POST   /api/agent/heartbeat/{hostId}         - Agent heartbeat (SECURED)
|
| Admin Endpoints:
| GET    /api/hosts                            - List hosts (SECURED)
| GET    /api/metrics                          - List metrics (SECURED)
| GET    /api/alerts                           - List alerts (SECURED)
| ... and all other management endpoints
|
| Test Endpoints:
| GET    /api/test/database                    - Test DB connection (SECURED)
| GET    /api/test/auth                        - Test authentication (SECURED)
|
| FIXED ROUTES:
| GET    /api/hosts/{host}/status              - Get host status (SECURED)
| GET    /api/connection-statuses              - List connection statuses (SECURED)
| GET    /api/connection-statuses/latest       - Get latest connection statuses (SECURED)
| GET    /api/metric-types/stats/overview      - Get metric types statistics (SECURED)
|
*/