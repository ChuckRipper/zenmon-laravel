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
    UserSessionController,
    NotificationController,
    UserController
};

/*
|=============================================================================
| ZenMon API Routes - Uporządkowane według poziomów bezpieczeństwa
|=============================================================================
|
| STRUKTURA:
| 1. PUBLICZNE (bez autentykacji)
| 2. AGENCI (tylko role Agent)
| 3. ADMINISTRATORZY (tylko role Administrator)
| 4. UŻYTKOWNICY+ (role User, Agent, Administrator)
|
| MIDDLEWARE:
| - auth:sanctum = podstawowe uwierzytelnienie
| - agent = tylko Agent
| - admin = tylko Administrator  
| - user+ = User + Agent + Administrator
|
*/

/*
|=============================================================================
| SEKCJA 1: PUBLICZNE ENDPOINTY (bez uwierzytelnienia)
|=============================================================================
*/

/// <summary>
/// Logowanie użytkownika - zwraca Bearer token
/// </summary>
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
                // 'user_id' => $user->user_id ?? $user->id,
                // 'user_id' => $user->id ?? $user->user_id,
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

/// <summary>
/// Publiczne endpointy - dostępne bez uwierzytelnienia
/// </summary>
Route::prefix('public')->group(function () {
    
    /// <summary>
    /// Health check aplikacji
    /// </summary>
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'ZenMon API',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString()
        ]);
    });

    /// <summary>
    /// Publiczne statystyki hostów
    /// </summary>
    Route::get('/hosts/count', [HostController::class, 'getPublicHostCount']);
    
    /// <summary>
    /// Publiczne statystyki alertów
    /// </summary>
    Route::get('/alerts/summary', [AlertController::class, 'getPublicAlertSummary']);
    
    /// <summary>
    /// Publiczne statystyki metryk
    /// </summary>
    Route::get('/metrics/summary', [MetricController::class, 'getPublicMetricsSummary']);
});

/*
|=============================================================================
| SEKCJA 2: ENDPOINTY WYMAGAJĄCE UWIERZYTELNIENIA
|=============================================================================
*/

Route::middleware(['auth:sanctum'])->group(function () {

    /*
    |-------------------------------------------------------------------------
    | PODSEKCJA 2.1: ENDPOINTY TESTOWE (tylko uwierzytelnienie)
    |-------------------------------------------------------------------------
    */
    
    Route::prefix('test')->group(function () {
        
        /// <summary>
        /// Test połączenia z bazą danych
        /// </summary>
        Route::get('/database', function () {
            try {
                \DB::connection()->getPdo();
                return response()->json([
                    'status' => 'connected', 
                    'database' => 'OK'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error', 
                    'message' => $e->getMessage()
                ], 500);
            }
        });

        /// <summary>
        /// Test uwierzytelnienia - informacje o zalogowanym użytkowniku
        /// </summary>
        Route::get('/auth', function (Request $request) {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'authenticated' => false,
                    'error' => 'User not found',
                    'timestamp' => now()->toISOString()
                ], 401);
            }
            
            $currentToken = $user->currentAccessToken();
            
            return response()->json([
                'authenticated' => true,
                'user' => [
                    // 'user_id' => $user->user_id ?? $user->id,
                    'user_id' => $user->id ?? $user->user_id,
                    'login' => $user->login,
                    // 'full_name' => $user->first_name . ' ' . $user->last_name,
                    'role' => $user->role,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name
                ],
                'token_name' => $currentToken ? $currentToken->name : 'unknown',
                'token_id' => $currentToken ? $currentToken->id : null,
                'timestamp' => now()->toISOString()
            ]);
        });
    });

    /*
    |-------------------------------------------------------------------------
    | PODSEKCJA 2.2: ENDPOINTY AGENTÓW (UC30, UC31)
    | Tylko role: Agent
    |-------------------------------------------------------------------------
    */
    
    Route::middleware('agent')->prefix('agent')->group(function () {
        
        /// <summary>
        /// UC31: Przesyłanie pojedynczej metryki przez agenta
        /// </summary>
        Route::post('/metrics', [MetricController::class, 'receiveFromAgent'])
            ->name('api.agent.metrics.submit');
        
        /// <summary>
        /// UC31: Przesyłanie pakietu metryk przez agenta
        /// </summary>
        Route::post('/metrics/batch', [MetricController::class, 'batchReceiveFromAgent'])
            ->name('api.agent.metrics.batch');
        
        /// <summary>
        /// Przesyłanie metryk katalogów przez agenta
        /// </summary>
        Route::post('/directory-metrics', [DirectoryMetricController::class, 'receiveFromAgent'])
            ->name('api.agent.directory-metrics.submit');
        
        /// <summary>
        /// UC30: Heartbeat agenta - potwierdzenie aktywności
        /// </summary>
        Route::post('/heartbeat/{hostId}', [ConnectionStatusController::class, 'receiveHeartbeat'])
            ->name('api.agent.heartbeat');
        
        /// <summary>
        /// UC30: Aktualizacja statusu agenta
        /// </summary>
        Route::post('/status/{hostId}', [HostController::class, 'updateAgentStatus'])
            ->name('api.agent.status.update');
        
        /// <summary>
        /// Pobranie konfiguracji dla agenta
        /// </summary>
        Route::get('/configuration/{hostId}', [HostConfigurationController::class, 'getAgentConfiguration'])
            ->name('api.agent.configuration');
        
        /// <summary>
        /// Pobranie listy monitorowanych katalogów dla agenta
        /// </summary>
        Route::get('/monitored-directories/{hostId}', [MonitoredDirectoryController::class, 'getAgentDirectories'])
            ->name('api.agent.directories');
    });

    /*
    |-------------------------------------------------------------------------
    | PODSEKCJA 2.3: ENDPOINTY ADMINISTRATORÓW (UC12, UC20-21, UC24, UC40, UC45)
    | Tylko role: Administrator
    |-------------------------------------------------------------------------
    */
    
    Route::middleware('admin')->group(function () {
        
        /*
        |---------------------------------------------------------------------
        | UC20-21: Zarządzanie hostami - operacje CRUD
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Tworzenie nowego hosta
        /// </summary>
        Route::post('/hosts', [HostController::class, 'store'])
            ->name('api.hosts.create');
        
        /// <summary>
        /// Aktualizacja hosta (PUT)
        /// </summary>
        Route::put('/hosts/{host}', [HostController::class, 'update'])
            ->name('api.hosts.update');
        
        /// <summary>
        /// Aktualizacja hosta (PATCH)
        /// </summary>
        Route::patch('/hosts/{host}', [HostController::class, 'update'])
            ->name('api.hosts.patch');
        
        /// <summary>
        /// Usuwanie hosta
        /// </summary>
        Route::delete('/hosts/{host}', [HostController::class, 'destroy'])
            ->name('api.hosts.delete');

        /*
        |---------------------------------------------------------------------
        | UC24: Konfiguracja hostów
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Aktualizacja konfiguracji hosta (POST)
        /// </summary>
        Route::post('/hosts/{host}/configuration', [HostController::class, 'updateConfiguration'])
            ->name('api.hosts.config.update');
        
        /// <summary>
        /// Aktualizacja konfiguracji hosta (PUT)
        /// </summary>
        Route::put('/hosts/{host}/configuration', [HostController::class, 'updateConfiguration'])
            ->name('api.hosts.config.update-put');
        
        /// <summary>
        /// Aktualizacja konfiguracji hosta (PATCH)
        /// </summary>
        Route::patch('/hosts/{host}/configuration', [HostController::class, 'updateConfiguration'])
            ->name('api.hosts.config.update-patch');
        
        /// <summary>
        /// Zarządzanie konfiguracjami hostów
        /// </summary>
        Route::post('/host-configurations', [HostConfigurationController::class, 'store'])
            ->name('api.host-configurations.create');
        Route::put('/host-configurations/{configuration}', [HostConfigurationController::class, 'update'])
            ->name('api.host-configurations.update');
        Route::delete('/host-configurations/{configuration}', [HostConfigurationController::class, 'destroy'])
            ->name('api.host-configurations.delete');

        /*
        |---------------------------------------------------------------------
        | UC40: Zarządzanie progami alertów
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Tworzenie progu alertu
        /// </summary>
        Route::post('/alert-thresholds', [AlertThresholdController::class, 'store'])
            ->name('api.alert-thresholds.create');
        
        /// <summary>
        /// Aktualizacja progu alertu
        /// </summary>
        Route::put('/alert-thresholds/{alert_threshold}', [AlertThresholdController::class, 'update'])
            ->name('api.alert-thresholds.update');
        
        /// <summary>
        /// Usuwanie progu alertu
        /// </summary>
        Route::delete('/alert-thresholds/{alert_threshold}', [AlertThresholdController::class, 'destroy'])
            ->name('api.alert-thresholds.delete');

        /// <summary>
        /// Rozwiązywanie alertu przez administratora
        /// </summary>
        Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])
            ->name('api.alerts.resolve');

        /*
        |---------------------------------------------------------------------
        | Zarządzanie monitorowanymi katalogami
        |---------------------------------------------------------------------
        */
        
        Route::post('/monitored-directories', [MonitoredDirectoryController::class, 'store'])
            ->name('api.monitored-directories.create');
        Route::put('/monitored-directories/{monitored_directory}', [MonitoredDirectoryController::class, 'update'])
            ->name('api.monitored-directories.update');
        Route::delete('/monitored-directories/{monitored_directory}', [MonitoredDirectoryController::class, 'destroy'])
            ->name('api.monitored-directories.delete');

        /*
        |---------------------------------------------------------------------
        | Zarządzanie typami metryk
        |---------------------------------------------------------------------
        */
        

        Route::post('/metric-types', [MetricTypeController::class, 'store'])
            ->name('api.metric-types.create');
        Route::put('/metric-types/{metric_type}', [MetricTypeController::class, 'update'])
            ->name('api.metric-types.update');
        Route::delete('/metric-types/{metric_type}', [MetricTypeController::class, 'destroy'])
            ->name('api.metric-types.delete');

        /*
        |---------------------------------------------------------------------
        | Zarządzanie alertami - operacje administratorskie
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Rozwiązywanie alertu przez administratora
        /// </summary>
        Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])
            ->name('api.alerts.resolve');
        
        /// <summary>
        /// Zamknięcie alertu przez administratora
        /// </summary>
        Route::put('/alerts/{alert}/close', [AlertController::class, 'close'])
            ->name('api.alerts.close');

        /// <summary>
        /// Potwierdzenie alertu przez administratora
        /// </summary>
        Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])
            ->name('api.alerts.acknowledge');

        /// <summary>
        /// Usuwanie alertu przez administratora
        /// </summary>
        Route::delete('/alerts/{alert}', [AlertController::class, 'destroy'])
            ->name('api.alerts.delete');

        /*
        |---------------------------------------------------------------------
        | UC12: Zarządzanie sesjami użytkowników
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Czyszczenie nieaktywnych sesji
        /// </summary>
        Route::post('/user-sessions/cleanup', [UserSessionController::class, 'cleanup'])
            ->name('api.user-sessions.cleanup');
        
        /// <summary>
        /// Usuwanie konkretnej sesji użytkownika
        /// </summary>
        Route::delete('/user-sessions/{user_session}', [UserSessionController::class, 'destroy'])
            ->name('api.user-sessions.delete');

        /*
        |---------------------------------------------------------------------
        | Zarządzanie użytkownikami - tylko administratorzy
        |---------------------------------------------------------------------
        */
        
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('api.users.list');
            Route::post('/', [UserController::class, 'store'])->name('api.users.create');
            Route::get('/{user}', [UserController::class, 'show'])->name('api.users.show');
            Route::put('/{user}', [UserController::class, 'update'])->name('api.users.update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('api.users.delete');
            Route::post('/{user}/activate', [UserController::class, 'activate'])->name('api.users.activate');
            Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('api.users.reset-password');
        });

        /*
        |---------------------------------------------------------------------
        | UC45: Zarządzanie powiadomieniami - tylko administratorzy
        |---------------------------------------------------------------------
        */
        
        Route::prefix('notifications')->group(function () {
            /// <summary>
            /// Pobranie konfiguracji powiadomień
            /// </summary>
            Route::get('/config', [NotificationController::class, 'getConfiguration'])
                ->name('api.notifications.config');
            
            /// <summary>
            /// Statystyki powiadomień
            /// </summary>
            Route::get('/stats', [NotificationController::class, 'getStatistics'])
                ->name('api.notifications.stats');
            
            /// <summary>
            /// Test wysyłania powiadomienia
            /// </summary>
            Route::post('/test', [NotificationController::class, 'testNotification'])
                ->name('api.notifications.test');
        });

        /*
        |---------------------------------------------------------------------
        | Operacje czyszczenia danych - tylko administratorzy
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Czyszczenie starych metryk
        /// </summary>
        Route::delete('/metrics/cleanup', [MetricController::class, 'cleanup'])
            ->name('api.metrics.cleanup');
        
        /// <summary>
        /// Usuwanie konkretnej metryki
        /// </summary>
        Route::delete('/metrics/{metric}', [MetricController::class, 'destroy'])
            ->name('api.metrics.delete');
    });

    /*
    |-------------------------------------------------------------------------
    | PODSEKCJA 2.4: ENDPOINTY UŻYTKOWNIKÓW+ (UC22-23, UC32-34, UC42-43)
    | Role: User + Agent + Administrator (hierarchia)
    |-------------------------------------------------------------------------
    */
    
    Route::middleware('user+')->group(function () {

        /*
        |---------------------------------------------------------------------
        | UC22-23: Zarządzanie hostami - operacje odczytu
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Lista hostów z filtrowaniem i paginacją
        /// </summary>
        Route::get('/hosts', [HostController::class, 'index'])
            ->name('api.hosts.list');
        
        /// <summary>
        /// Szczegóły konkretnego hosta
        /// </summary>
        Route::get('/hosts/{host}', [HostController::class, 'show'])
            ->name('api.hosts.show');
        
        /// <summary>
        /// UC32: Metryki konkretnego hosta
        /// </summary>
        Route::get('/hosts/{host}/metrics', [HostController::class, 'getMetrics'])
            ->name('api.hosts.metrics');
        
        /// <summary>
        /// Alerty konkretnego hosta
        /// </summary>
        Route::get('/hosts/{host}/alerts', [HostController::class, 'getAlerts'])
            ->name('api.hosts.alerts');
        
        /// <summary>
        /// UC23: Status połączenia z hostem
        /// </summary>
        Route::get('/hosts/{host}/status', [HostController::class, 'getHostStatus'])
            ->name('api.hosts.status');
        
        /// <summary>
        /// Wyszukiwanie hostów w sieci
        /// </summary>
        Route::get('/hosts/search/network', [HostController::class, 'searchInNetwork'])
            ->name('api.hosts.search.network');

        /*
        |---------------------------------------------------------------------
        | Konfiguracje hostów - operacje odczytu
        |---------------------------------------------------------------------
        */
        
        Route::get('/host-configurations', [HostConfigurationController::class, 'index'])
            ->name('api.host-configurations.list');
        Route::get('/host-configurations/{configuration}', [HostConfigurationController::class, 'show'])
            ->name('api.host-configurations.show');

        /*
        |---------------------------------------------------------------------
        | UC32-33: Metryki - operacje odczytu i analiza
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Lista metryk z filtrowaniem
        /// </summary>
        Route::get('/metrics', [MetricController::class, 'index'])
            ->name('api.metrics.list');
        
        /// <summary>
        /// Szczegóły konkretnej metryki
        /// </summary>
        Route::get('/metrics/{metric}', [MetricController::class, 'show'])
            ->name('api.metrics.show');
        
        /// <summary>
        /// UC33: Dane historyczne metryk
        /// </summary>
        Route::get('/metrics/historical', [MetricController::class, 'getHistorical'])
            ->name('api.metrics.historical');
        
        /// <summary>
        /// UC32: Najnowsze metryki dla hosta
        /// </summary>
        Route::get('/metrics/latest/{host}', [MetricController::class, 'getLatestByHost'])
            ->name('api.metrics.latest');

        /*
        |---------------------------------------------------------------------
        | Typy metryk - operacje odczytu
        |---------------------------------------------------------------------
        */
        
        Route::get('/metric-types', [MetricTypeController::class, 'index'])
            ->name('api.metric-types.list');
        Route::get('/metric-types/{metric_type}', [MetricTypeController::class, 'show'])
            ->name('api.metric-types.show');
        Route::get('/metric-types/stats', [MetricTypeController::class, 'getWithStats'])
            ->name('api.metric-types.stats');

        /*
        |---------------------------------------------------------------------
        | UC42-43: Alerty - operacje odczytu i potwierdzanie
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Lista alertów z filtrowaniem
        /// </summary>
        Route::get('/alerts', [AlertController::class, 'index'])
            ->name('api.alerts.list');
        
        /// <summary>
        /// Szczegóły konkretnego alertu
        /// </summary>
        Route::get('/alerts/{alert}', [AlertController::class, 'show'])
            ->name('api.alerts.show');
        
        /// <summary>
        /// UC42: Potwierdzenie alertu przez użytkownika
        /// </summary>
        Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])
            ->name('api.alerts.acknowledge');
        
        /// <summary>
        /// UC43: Zamknięcie alertu przez użytkownika z komentarzem
        /// </summary>
        Route::put('/alerts/{alert}/close', [AlertController::class, 'close'])
            ->name('api.alerts.close');
        
        /// <summary>
        /// Dane dashboardu alertów
        /// </summary>
        Route::get('/alerts/dashboard', [AlertController::class, 'getDashboardData'])
            ->name('api.alerts.dashboard');

        /*
        |---------------------------------------------------------------------
        | Progi alertów - operacje odczytu
        |---------------------------------------------------------------------
        */
        
        Route::get('/alert-thresholds', [AlertThresholdController::class, 'index'])
            ->name('api.alert-thresholds.list');
        Route::get('/alert-thresholds/{alert_threshold}', [AlertThresholdController::class, 'show'])
            ->name('api.alert-thresholds.show');

        /*
        |---------------------------------------------------------------------
        | Status połączeń - operacje odczytu
        |---------------------------------------------------------------------
        */
        
        Route::get('/connection-statuses', [ConnectionStatusController::class, 'index'])
            ->name('api.connection-statuses.list');
        Route::get('/connection-statuses/latest', [ConnectionStatusController::class, 'getLatestStatuses'])
            ->name('api.connection-statuses.latest');
        Route::get('/connection-statuses/host/{hostId}/statistics', [ConnectionStatusController::class, 'getHostStatistics'])
            ->name('api.connection-statuses.host-stats');
        Route::post('/connection-status/check', [ConnectionStatusController::class, 'checkConnection'])
            ->name('api.connection-status.check');

        /*
        |---------------------------------------------------------------------
        | Monitorowane katalogi - operacje odczytu
        |---------------------------------------------------------------------
        */
        
        Route::get('/monitored-directories', [MonitoredDirectoryController::class, 'index'])
            ->name('api.monitored-directories.list');
        Route::get('/monitored-directories/{monitored_directory}', [MonitoredDirectoryController::class, 'show'])
            ->name('api.monitored-directories.show');
        Route::get('/monitored-directories/host/{host}', [MonitoredDirectoryController::class, 'getByHost'])
            ->name('api.monitored-directories.by-host');

        /*
        |---------------------------------------------------------------------
        | Metryki katalogów - operacje odczytu
        |---------------------------------------------------------------------
        */
        
        Route::get('/directory-metrics', [DirectoryMetricController::class, 'index'])
            ->name('api.directory-metrics.list');
        Route::get('/directory-metrics/{directoryMetric}', [DirectoryMetricController::class, 'show'])
            ->name('api.directory-metrics.show');
        Route::get('/directory-metrics/directory/{directory}', [DirectoryMetricController::class, 'getByDirectory'])
            ->name('api.directory-metrics.by-directory');

        /*
        |---------------------------------------------------------------------
        | Sesje użytkowników - zarządzanie własnymi sesjami
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Pobranie własnych aktywnych sesji
        /// </summary>
        Route::get('/user-sessions/active', [UserSessionController::class, 'getActiveSessions'])
            ->name('api.user-sessions.active');

        /*
        |---------------------------------------------------------------------
        | Zarządzanie własnym profilem
        |---------------------------------------------------------------------
        */
        
        /// <summary>
        /// Wylogowanie - usunięcie tokenu
        /// </summary>
        Route::post('/logout', function (Request $request) {
            $request->user()->currentAccessToken()->delete();
            return response()->json([
                'message' => 'Successfully logged out'
            ]);
        })->name('api.auth.logout');

        /// <summary>
        /// Informacje o zalogowanym użytkowniku
        /// </summary>
        Route::get('/user', function (Request $request) {
            $user = $request->user();
            return response()->json([
                'login' => $user->login,
                'email' => $user->email,
                'role' => $user->role,
                'token_name' => $user->currentAccessToken()->name,
                'timestamp' => now()->toISOString()
            ]);
        })->name('api.auth.user');
        
        /// <summary>
        /// Zarządzanie profilem użytkownika
        /// </summary>
        Route::prefix('user')->group(function () {
            Route::get('/profile', [UserController::class, 'profile'])->name('api.user.profile');
            Route::put('/profile', [UserController::class, 'updateProfile'])->name('api.user.update-profile');
            Route::post('/change-password', [UserController::class, 'changePassword'])->name('api.user.change-password');
        });
    });
});

/*
|=============================================================================
| DOKUMENTACJA ENDPOINTÓW
|=============================================================================
|
| PUBLICZNE (bez auth):
| POST   /api/login                     - Logowanie
| GET    /api/public/health             - Health check
| GET    /api/public/hosts/count        - Statystyki hostów
| GET    /api/public/alerts/summary     - Statystyki alertów
| GET    /api/public/metrics/summary    - Statystyki metryk
|
| TESTY (auth):
| GET    /api/test/database             - Test bazy danych
| GET    /api/test/auth                 - Test uwierzytelnienia
|
| AGENCI (auth + agent):
| POST   /api/agent/metrics             - Pojedyncza metryka
| POST   /api/agent/metrics/batch       - Pakiet metryk
| POST   /api/agent/directory-metrics   - Metryki katalogów
| POST   /api/agent/heartbeat/{hostId}  - Heartbeat
| POST   /api/agent/status/{hostId}     - Status agenta
| GET    /api/agent/configuration/{hostId} - Konfiguracja
| GET    /api/agent/monitored-directories/{hostId} - Katalogi
|
| ADMINISTRATORZY (auth + admin):
| CRUD   /api/hosts                     - Zarządzanie hostami
| CRUD   /api/host-configurations       - Konfiguracje hostów
| CRUD   /api/alert-thresholds          - Progi alertów
| CRUD   /api/monitored-directories     - Katalogi
| CRUD   /api/metric-types              - Typy metryk
| CRUD   /api/users                     - Użytkownicy
| GET    /api/notifications/*           - Powiadomienia
| POST   /api/alerts/{id}/resolve       - Rozwiązywanie alertów
| POST   /api/user-sessions/cleanup     - Czyszczenie sesji
|
| UŻYTKOWNICY+ (auth + user+):
| GET    /api/hosts                     - Lista hostów
| GET    /api/hosts/{id}/*              - Szczegóły hostów
| GET    /api/metrics                   - Metryki
| GET    /api/metrics/historical        - Historia metryk
| GET    /api/alerts                    - Alerty
| POST   /api/alerts/{id}/acknowledge   - Potwierdzanie alertów
| GET    /api/connection-statuses       - Statusy połączeń
| POST   /api/logout                    - Wylogowanie
| GET    /api/user                      - Profil użytkownika
|
*/