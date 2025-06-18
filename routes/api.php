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
    // Health check endpoint
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'service' => 'ZenMon API'
        ]);
    });
    
    // Agent endpoints (without authentication for easier agent setup)
    Route::post('/metrics', [MetricController::class, 'store'])->name('public.metrics.store');
    Route::post('/metrics/batch', [MetricController::class, 'storeBatch'])->name('public.metrics.batch');
    
    // Public metric types list (for agent configuration)
    Route::get('/metric-types', [MetricTypeController::class, 'index'])->name('public.metric-types');
});

// Authentication required routes
Route::middleware('auth:sanctum')->group(function () {
    /// <summary>
    /// Get current authenticated user information
    /// </summary>
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Main API Resources (UC20, UC21, UC22, UC44)
    Route::apiResource('hosts', HostController::class);
    Route::apiResource('alerts', AlertController::class);
    Route::apiResource('metrics', MetricController::class);
    Route::apiResource('metric-types', MetricTypeController::class);
    
    // Custom Host endpoints
    Route::prefix('hosts/{host}')->group(function () {
        Route::get('/metrics', [HostController::class, 'metrics'])->name('hosts.metrics');
        Route::get('/status', [HostController::class, 'status'])->name('hosts.status');
        Route::get('/alerts', [HostController::class, 'alerts'])->name('hosts.alerts');
    })->where('host', '[0-9]+');
    
    // Custom Metric endpoints (UC30, UC31, UC32, UC33)
    Route::prefix('metrics')->group(function () {
        /// <summary>
        /// Store multiple metrics in batch (for agent efficiency)
        /// </summary>
        Route::post('/batch', [MetricController::class, 'storeBatch'])->name('metrics.batch');
        
        /// <summary>
        /// Get latest metrics for specific host (UC32: View current metrics)
        /// </summary>
        Route::get('/latest/{hostId}', [MetricController::class, 'getLatestByHost'])
             ->name('metrics.latest-by-host')
             ->where('hostId', '[0-9]+');
        
        /// <summary>
        /// Get historical metrics for trending (UC33: View historical data)
        /// </summary>
        Route::get('/historical', [MetricController::class, 'getHistorical'])->name('metrics.historical');
        
        /// <summary>
        /// Delete old metrics (maintenance endpoint)
        /// </summary>
        Route::delete('/cleanup', [MetricController::class, 'cleanup'])->name('metrics.cleanup');
    });
    
    // Custom Metric Type endpoints
    Route::prefix('metric-types')->group(function () {
        /// <summary>
        /// Get metric types with statistics (dashboard data)
        /// </summary>
        Route::get('/stats', [MetricTypeController::class, 'getWithStats'])->name('metric-types.stats');
        
        /// <summary>
        /// Get available units for creating metric types
        /// </summary>
        Route::get('/units', [MetricTypeController::class, 'getAvailableUnits'])->name('metric-types.units');
    });
    
    // Other API Resources (to be implemented)
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
*/