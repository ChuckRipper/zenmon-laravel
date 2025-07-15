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
| API Routes - ZenMon Secure Implementation with Role Hierarchy
|--------------------------------------------------------------------------
|
| SECURITY MODEL:
| - UNAUTHENTICATED: /login, /public/*
| - AGENT: /agent/* (UC30, UC31 - tylko Agent)
| - ADMIN: CRUD operations (UC12, UC20-21, UC24, UC40, UC45)
| - USER+: Read operations (UC22-23, UC32-34, UC42-43) - User + Administrator
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
    | AGENT ONLY ENDPOINTS (UC30, UC31 - tylko Agent)
    |--------------------------------------------------------------------------
    */
    Route::middleware('agent')->prefix('agent')->group(function () {
        
        // UC31: Metric submission from agents (TYLKO Agent)
        Route::post('/metrics', [MetricController::class, 'receiveFromAgent'])->name('api.agent.metrics.submit');
        Route::post('/metrics/batch', [MetricController::class, 'batchReceiveFromAgent'])->name('api.agent.metrics.batch');
        
        // Directory metrics from agents
        Route::post('/directory-metrics', [DirectoryMetricController::class, 'receiveFromAgent'])->name('api.agent.directory-metrics.submit');
        
        // UC30: Agent heartbeat and status (TYLKO Agent)
        Route::post('/heartbeat/{hostId}', [ConnectionStatusController::class, 'receiveHeartbeat'])->name('api.agent.heartbeat');
        Route::post('/status/{hostId}', [HostController::class, 'updateAgentStatus'])->name('api.agent.status.update');
        
        // Agent configuration retrieval (TYLKO Agent)
        Route::get('/configuration/{hostId}', [HostConfigurationController::class, 'getAgentConfiguration'])->name('api.agent.configuration');
        Route::get('/monitored-directories/{hostId}', [MonitoredDirectoryController::class, 'getAgentDirectories'])->name('api.agent.directories');
        
    });

    /*
    |--------------------------------------------------------------------------
    | ADMINISTRATOR ONLY ENDPOINTS (UC12, UC20-21, UC24, UC40, UC45)
    | TYLKO CREATE, UPDATE, DELETE - BEZ GET (te są w user+)
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->group(function () {
        
        // UC20-21: Host Management - Admin operations (TYLKO CRUD)
        Route::post('/hosts', [HostController::class, 'store'])->name('api.hosts.create');
        Route::put('/hosts/{host}', [HostController::class, 'update'])->name('api.hosts.update');
        Route::patch('/hosts/{host}', [HostController::class, 'update'])->name('api.hosts.patch');
        Route::delete('/hosts/{host}', [HostController::class, 'destroy'])->name('api.hosts.delete');
        
        // UC24: Host Configuration - Admin only (TYLKO CRUD)
        Route::post('/hosts/{host}/configuration', [HostController::class, 'updateConfiguration'])->name('api.hosts.config.update');
        Route::post('/host-configurations', [HostConfigurationController::class, 'store'])->name('api.host-configurations.create');
        Route::put('/host-configurations/{configuration}', [HostConfigurationController::class, 'update'])->name('api.host-configurations.update');
        Route::delete('/host-configurations/{configuration}', [HostConfigurationController::class, 'destroy'])->name('api.host-configurations.delete');
        Route::post('/host-configurations/bulk', [HostConfigurationController::class, 'bulkUpdate'])->name('api.host-configurations.bulk');
        
        // UC40: Alert Thresholds - Admin only (TYLKO CRUD)
        Route::post('/alert-thresholds', [AlertThresholdController::class, 'store'])->name('api.alert-thresholds.create');
        Route::put('/alert-thresholds/{alert_threshold}', [AlertThresholdController::class, 'update'])->name('api.alert-thresholds.update');
        Route::delete('/alert-thresholds/{alert_threshold}', [AlertThresholdController::class, 'destroy'])->name('api.alert-thresholds.delete');
        Route::post('/alert-thresholds/bulk', [AlertThresholdController::class, 'bulkCreate'])->name('api.alert-thresholds.bulk');
        
        // Monitored Directories - Admin operations (TYLKO CRUD)
        Route::post('/monitored-directories', [MonitoredDirectoryController::class, 'store'])->name('api.monitored-directories.create');
        Route::put('/monitored-directories/{monitored_directory}', [MonitoredDirectoryController::class, 'update'])->name('api.monitored-directories.update');
        Route::delete('/monitored-directories/{monitored_directory}', [MonitoredDirectoryController::class, 'destroy'])->name('api.monitored-directories.delete');
        Route::post('/monitored-directories/bulk', [MonitoredDirectoryController::class, 'bulkCreate'])->name('api.monitored-directories.bulk');
        
        // Metric Types - Admin only (TYLKO CRUD)
        Route::post('/metric-types', [MetricTypeController::class, 'store'])->name('api.metric-types.create');
        Route::put('/metric-types/{metric_type}', [MetricTypeController::class, 'update'])->name('api.metric-types.update');
        Route::delete('/metric-types/{metric_type}', [MetricTypeController::class, 'destroy'])->name('api.metric-types.delete');
        
        // Metrics - Admin cleanup operations (TYLKO DELETE)
        Route::delete('/metrics/cleanup', [MetricController::class, 'cleanup'])->name('api.metrics.cleanup');
        Route::delete('/metrics/{metric}', [MetricController::class, 'destroy'])->name('api.metrics.delete');
        
        // Alerts - Admin resolution operations (TYLKO CRUD)
        Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('api.alerts.resolve');
        Route::delete('/alerts/{alert}', [AlertController::class, 'destroy'])->name('api.alerts.delete');
        
        // UC12: User Sessions - Admin management (TYLKO DELETE)
        Route::post('/user-sessions/cleanup', [UserSessionController::class, 'cleanup'])->name('api.user-sessions.cleanup');
        Route::delete('/user-sessions/{user_session}', [UserSessionController::class, 'destroy'])->name('api.user-sessions.delete');
        
    });

    /*
    |--------------------------------------------------------------------------
    | USER OR ADMINISTRATOR ENDPOINTS (UC22-23, UC32-34, UC42-43)
    | User = tylko odczyt, Administrator = odczyt + wszystko z sekcji admin
    |--------------------------------------------------------------------------
    */
    Route::middleware('user+')->group(function () {
        
        // UC22-23: Host Management - Read operations (User + Admin)
        // Route::get('/hosts', [HostController::class, 'index'])->name('api.hosts.list');
        Route::get('/hosts', [HostController::class, 'index'])->name('api.hosts.list-readonly');
        Route::get('/hosts/{host}', [HostController::class, 'show'])->name('api.hosts.show');
        Route::get('/hosts/{host}/metrics', [HostController::class, 'getMetrics'])->name('api.hosts.metrics');
        Route::get('/hosts/{host}/alerts', [HostController::class, 'getAlerts'])->name('api.hosts.alerts');
        Route::get('/hosts/{host}/status', [HostController::class, 'getHostStatus'])->name('api.hosts.status');
        Route::get('/hosts/search/network', [HostController::class, 'searchInNetwork'])->name('api.hosts.search.network');
        
        // Host Configuration - Read operations (User + Admin)
        Route::get('/host-configurations', [HostConfigurationController::class, 'index'])->name('api.host-configurations.list-read');
        Route::get('/host-configurations/{configuration}', [HostConfigurationController::class, 'show'])->name('api.host-configurations.show-read');
        
        // UC23: Connection Status - Read operations (User + Admin)
        Route::get('/connection-status', [ConnectionStatusController::class, 'index'])->name('api.connection-status.list');
        Route::get('/connection-statuses', [ConnectionStatusController::class, 'index'])->name('api.connection-statuses.list');
        Route::get('/connection-status/{connectionStatus}', [ConnectionStatusController::class, 'show'])->name('api.connection-status.show');
        Route::get('/connection-statuses/latest', [ConnectionStatusController::class, 'getLatestStatuses'])->name('api.connection-statuses.latest');
        Route::get('/connection-statuses/host/{hostId}/statistics', [ConnectionStatusController::class, 'getHostStatistics'])->name('api.connection-statuses.host-stats');
        Route::post('/connection-status/check', [ConnectionStatusController::class, 'checkConnection'])->name('api.connection-status.check');
        
        // Monitored Directories - Read operations (User + Admin)
        Route::get('/monitored-directories', [MonitoredDirectoryController::class, 'index'])->name('api.monitored-directories.list-read');
        Route::get('/monitored-directories/{monitored_directory}', [MonitoredDirectoryController::class, 'show'])->name('api.monitored-directories.show-read');
        Route::get('/monitored-directories/host/{host}', [MonitoredDirectoryController::class, 'getByHost'])->name('api.monitored-directories.by-host');
        
        // Directory Metrics - Read operations (User + Admin)
        Route::get('/directory-metrics', [DirectoryMetricController::class, 'index'])->name('api.directory-metrics.list');
        Route::get('/directory-metrics/{directoryMetric}', [DirectoryMetricController::class, 'show'])->name('api.directory-metrics.show');
        Route::get('/directory-metrics/directory/{directory}', [DirectoryMetricController::class, 'getByDirectory'])->name('api.directory-metrics.by-directory');
        Route::post('/directory-metrics/batch', [DirectoryMetricController::class, 'batchStore'])->name('api.directory-metrics.batch');
        
        // UC32-33: Metrics - Read operations (User + Admin)
        Route::get('/metrics', [MetricController::class, 'index'])->name('api.metrics.list');
        Route::get('/metrics/{metric}', [MetricController::class, 'show'])->name('api.metrics.show');
        Route::get('/metrics/latest/{host}', [MetricController::class, 'getLatestByHost'])->name('api.metrics.latest');
        Route::get('/metrics/historical', [MetricController::class, 'getHistorical'])->name('api.metrics.historical');
        Route::post('/metrics/batch', [MetricController::class, 'batchStore'])->name('api.metrics.batch');
        
        // Metric Types - Read operations (User + Admin)
        Route::get('/metric-types', [MetricTypeController::class, 'index'])->name('api.metric-types.list');
        Route::get('/metric-types/{metric_type}', [MetricTypeController::class, 'show'])->name('api.metric-types.show');
        Route::get('/metric-types/stats', [MetricTypeController::class, 'getWithStats'])->name('api.metric-types.stats');
        Route::get('/metric-types/units', [MetricTypeController::class, 'getAvailableUnits'])->name('api.metric-types.units');
        Route::get('/metric-types/usage/statistics', [MetricTypeController::class, 'getUsageStatistics'])->name('api.metric-types.usage-stats');
        
        // UC42-43: Alerts - Read and acknowledge operations (User + Admin)
        Route::get('/alerts', [AlertController::class, 'index'])->name('api.alerts.list');
        Route::get('/alerts/{alert}', [AlertController::class, 'show'])->name('api.alerts.show');
        Route::get('/alerts/dashboard', [AlertController::class, 'getDashboardData'])->name('api.alerts.dashboard');
        Route::post('/alerts/acknowledge/{alert}', [AlertController::class, 'acknowledge'])->name('api.alerts.acknowledge');
        Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('api.alerts.acknowledge-alt');
        Route::put('/alerts/{alert}', [AlertController::class, 'update'])->name('api.alerts.update');  //
        
        // Alert Thresholds - Read operations (User + Admin)
        Route::get('/alert-thresholds', [AlertThresholdController::class, 'index'])->name('api.alert-thresholds.list-read');
        Route::get('/alert-thresholds/{alert_threshold}', [AlertThresholdController::class, 'show'])->name('api.alert-thresholds.show-read');
        
        // User Sessions - Self-management (User + Admin)
        Route::get('/user-sessions/active', [UserSessionController::class, 'getActiveSessions'])->name('api.user-sessions.active');
        
        // User token management (User + Admin)
        Route::post('/logout', function (Request $request) {
            $request->user()->currentAccessToken()->delete();
            
            return response()->json([
                'message' => 'Successfully logged out'
            ]);
        })->name('api.auth.logout');

        Route::get('/user', function (Request $request) {
            return response()->json([
                'user' => $request->user(),
                'token_name' => $request->user()->currentAccessToken()->name,
                'timestamp' => now()->toISOString()
            ]);
        })->name('api.auth.user');
        
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
| AGENT ONLY (UC30, UC31):
| POST   /api/agent/metrics                    - Submit single metric
| POST   /api/agent/metrics/batch              - Submit metrics batch
| POST   /api/agent/directory-metrics          - Submit directory metrics
| POST   /api/agent/heartbeat/{hostId}         - Agent heartbeat
| POST   /api/agent/status/{hostId}            - Update agent status
| GET    /api/agent/configuration/{hostId}     - Get agent configuration
| GET    /api/agent/monitored-directories/{hostId} - Get monitored directories
|
| ADMINISTRATOR ONLY (UC12, UC20-21, UC24, UC40, UC45):
| POST   /api/hosts                            - Create host
| PUT    /api/hosts/{host}                     - Update host
| DELETE /api/hosts/{host}                     - Delete host
| POST   /api/host-configurations              - Create host configuration
| PUT    /api/host-configurations/{config}     - Update host configuration
| DELETE /api/host-configurations/{config}     - Delete host configuration
| POST   /api/alert-thresholds                 - Create alert threshold
| PUT    /api/alert-thresholds/{threshold}     - Update alert threshold
| DELETE /api/alert-thresholds/{threshold}     - Delete alert threshold
| ... and all other CRUD operations
|
| USER + ADMINISTRATOR (UC22-23, UC32-34, UC42-43):
| GET    /api/hosts                            - List hosts
| GET    /api/hosts/{host}                     - Show host details
| GET    /api/hosts/{host}/metrics             - Get host metrics
| GET    /api/hosts/{host}/alerts              - Get host alerts
| GET    /api/metrics                          - List metrics
| GET    /api/metrics/historical               - Get historical metrics
| GET    /api/alerts                           - List alerts
| POST   /api/alerts/acknowledge/{alert}       - Acknowledge alert
| ... and all other read operations
|
| Test Endpoints:
| GET    /api/test/database                    - Test DB connection
| GET    /api/test/auth                        - Test authentication
|
*/