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
    ConnectionStatusController
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

// Login endpoint dla API
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
                'full_name' => $user->full_name,
                'role' => $user->role
            ]
        ]);
    }

    return response()->json(['message' => 'Invalid credentials'], 401);
});

// Public endpoints (for agents and health checks)
Route::prefix('public')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'service' => 'ZenMon API'
        ]);
    });
    
    Route::post('/metrics', [MetricController::class, 'store'])->name('public.metrics.store');
    Route::post('/metrics/batch', [MetricController::class, 'storeBatch'])->name('public.metrics.batch');
    Route::get('/metric-types', [MetricTypeController::class, 'index'])->name('public.metric-types');
});

// Authentication required routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Custom routes BEFORE apiResource
    Route::get('/hosts/{host}/metrics', [HostController::class, 'metrics'])->name('hosts.metrics')->where('host', '[0-9]+');
    Route::get('/hosts/{host}/status', [HostController::class, 'status'])->name('hosts.status')->where('host', '[0-9]+');
    Route::get('/hosts/{host}/alerts', [HostController::class, 'alerts'])->name('hosts.alerts')->where('host', '[0-9]+');
    
    Route::post('/metrics/batch', [MetricController::class, 'storeBatch'])->name('metrics.batch');
    Route::get('/metrics/latest/{hostId}', [MetricController::class, 'getLatestByHost'])->name('metrics.latest-by-host')->where('hostId', '[0-9]+');
    Route::get('/metrics/historical', [MetricController::class, 'getHistorical'])->name('metrics.historical');
    Route::delete('/metrics/cleanup', [MetricController::class, 'cleanup'])->name('metrics.cleanup');
    
    Route::get('/metric-types/stats', [MetricTypeController::class, 'getWithStats'])->name('metric-types.stats');
    Route::get('/metric-types/units', [MetricTypeController::class, 'getAvailableUnits'])->name('metric-types.units');
    
    Route::get('/connection-statuses/latest', [ConnectionStatusController::class, 'getLatestStatuses'])->name('connection-statuses.latest');
    Route::get('/connection-statuses/host/{hostId}/statistics', [ConnectionStatusController::class, 'getHostStatistics'])->name('connection-statuses.host-statistics')->where('hostId', '[0-9]+');
    Route::delete('/connection-statuses/cleanup', [ConnectionStatusController::class, 'cleanup'])->name('connection-statuses.cleanup');

    // Main API Resources AFTER custom routes
    Route::apiResource('hosts', HostController::class);
    Route::apiResource('alerts', AlertController::class);
    Route::apiResource('metrics', MetricController::class);
    Route::apiResource('metric-types', MetricTypeController::class);
    Route::apiResource('host-configurations', HostConfigurationController::class);
    Route::apiResource('alert-thresholds', AlertThresholdController::class);
    Route::apiResource('connection-statuses', ConnectionStatusController::class);
});

/*
|--------------------------------------------------------------------------
| API Routes Summary
|--------------------------------------------------------------------------
|
| Public Endpoints (no auth):
| GET    /api/public/health              - Health check
| POST   /api/public/metrics             - Agent sends single metric
| POST   /api/public/metrics/batch       - Agent sends multiple metrics
| GET    /api/public/metric-types        - Get metric types list
|
| Authenticated Endpoints:
| POST   /api/login                      - User authentication
| GET    /api/user                       - Current user info
|
| Hosts API (UC20, UC21, UC22, UC23):
| GET    /api/hosts                      - List hosts
| POST   /api/hosts                      - Create host
| GET    /api/hosts/{id}                 - Show host
| PUT    /api/hosts/{id}                 - Update host
| DELETE /api/hosts/{id}                 - Delete host
| GET    /api/hosts/{id}/metrics         - Host metrics
| GET    /api/hosts/{id}/status          - Host status
| GET    /api/hosts/{id}/alerts          - Host alerts
|
| Metrics API (UC30, UC31, UC32, UC33):
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
| Alerts API (UC44: API access):
| GET    /api/alerts                     - List alerts
| POST   /api/alerts                     - Create alert
| GET    /api/alerts/{id}                - Show alert
| PUT    /api/alerts/{id}                - Update alert
| DELETE /api/alerts/{id}                - Delete alert
|
| Alert Thresholds API (UC40):
| GET    /api/alert-thresholds           - List alert thresholds
| POST   /api/alert-thresholds           - Create alert threshold  
| GET    /api/alert-thresholds/{id}      - Show alert threshold
| PUT    /api/alert-thresholds/{id}      - Update alert threshold
| DELETE /api/alert-thresholds/{id}      - Delete alert threshold
|
| Connection Status API (UC23):
| GET    /api/connection-statuses        - List connection statuses
| POST   /api/connection-statuses        - Store connection status
| GET    /api/connection-statuses/{id}   - Show connection status
| GET    /api/connection-statuses/latest - Latest status for all hosts
| GET    /api/connection-statuses/host/{hostId}/statistics - Host statistics
| DELETE /api/connection-statuses/cleanup - Cleanup old statuses
|
*/