<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Laravel\Sanctum\Sanctum;

/// <summary>
/// Main application service provider for ZenMon monitoring system
/// Handles application-wide service registration and bootstrapping
/// </summary>
class AppServiceProvider extends ServiceProvider
{
    #region Properties

    /// <summary>
    /// All of the container singletons that should be registered
    /// </summary>
    public array $singletons = [
        // Add any singleton services here if needed
    ];

    /// <summary>
    /// All of the container bindings that should be registered
    /// </summary>
    public array $bindings = [
        // Add any service bindings here if needed
    ];

    #endregion

    #region Methods

    /// <summary>
    /// Register any application services
    /// </summary>
    /// <returns>void</returns>
    public function register(): void
    {
        // Register custom monitoring services
        $this->registerMonitoringServices();

        // Register API services
        $this->registerApiServices();

        // Register development-specific services
        if ($this->app->environment('local', 'testing')) {
            $this->registerDevelopmentServices();
        }
    }

    /// <summary>
    /// Bootstrap any application services
    /// </summary>
    /// <returns>void</returns>
    public function boot(): void
    {
        // Set default database string length for older MySQL versions
        Schema::defaultStringLength(191);

        // Configure Eloquent model behavior
        $this->configureEloquentBehavior();

        // Configure pagination
        $this->configurePagination();

        // Configure authentication
        $this->configureAuthentication();

        // Configure database query logging in development
        $this->configureQueryLogging();

        // Configure model events for auditing
        $this->configureModelEvents();
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Register development-specific services
    /// </summary>
    /// <returns>void</returns>
    private function registerDevelopmentServices(): void
    {
        // Additional development services can be registered here
        // Telescope is already registered via providers.php
        
        // Example: Register debug-specific services
        $this->app->singleton('debug.profiler', function ($app) {
            return new \App\Services\DebugProfilerService();
        });
    }

    /// <summary>
    /// Register custom monitoring services
    /// </summary>
    /// <returns>void</returns>
    private function registerMonitoringServices(): void
    {
        // Register any custom monitoring services here
        // Example: Agent connection services, metric processors, etc.
        
        $this->app->singleton('zenmon.agent.connector', function ($app) {
            return new \App\Services\AgentConnectorService(
                $app['config']->get('zenmon.agent_timeout', 5),
                $app['config']->get('zenmon.agent_port', 8080)
            );
        });
    }

    /// <summary>
    /// Register API-related services
    /// </summary>
    /// <returns>void</returns>
    private function registerApiServices(): void
    {
        // Configure API response transformation
        $this->app->singleton('api.transformer', function ($app) {
            return new \App\Services\ApiTransformerService();
        });
    }

    /// <summary>
    /// Configure Eloquent model behavior
    /// </summary>
    /// <returns>void</returns>
    private function configureEloquentBehavior(): void
    {
        // Prevent lazy loading in non-production environments
        if ($this->app->environment('local', 'testing')) {
            Model::preventLazyLoading();
        }

        // Prevent silently discarding attributes
        Model::preventSilentlyDiscardingAttributes();

        // Prevent accessing missing attributes
        if ($this->app->environment('local', 'testing')) {
            Model::preventAccessingMissingAttributes();
        }
    }

    /// <summary>
    /// Configure pagination settings
    /// </summary>
    /// <returns>void</returns>
    private function configurePagination(): void
    {
        // Use Bootstrap 4 for pagination views
        Paginator::defaultView('pagination::bootstrap-4');
        Paginator::defaultSimpleView('pagination::simple-bootstrap-4');
    }

    /// <summary>
    /// Configure authentication settings
    /// </summary>
    /// <returns>void</returns>
    private function configureAuthentication(): void
    {
        // Configure Sanctum for API authentication
        Sanctum::usePersonalAccessTokenModel(\Laravel\Sanctum\PersonalAccessToken::class);

        // Set token expiration (8 hours)
        config(['sanctum.expiration' => 480]);
    }

    /// <summary>
    /// Configure database query logging for development
    /// </summary>
    /// <returns>void</returns>
    private function configureQueryLogging(): void
    {
        if ($this->app->environment('local') && config('app.debug')) {
            \DB::listen(function ($query) {
                if ($query->time > 1000) { // Log slow queries (> 1 second)
                    \Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms'
                    ]);
                }
            });
        }
    }

    /// <summary>
    /// Configure model events for auditing
    /// </summary>
    /// <returns>void</returns>
    private function configureModelEvents(): void
    {
        // Log important model events for auditing
        $modelsToAudit = [
            \App\Models\Host::class,
            \App\Models\Alert::class,
            \App\Models\AlertThreshold::class,
            \App\Models\User::class
        ];

        foreach ($modelsToAudit as $model) {
            $model::creating(function ($model) {
                \Log::info('Model creating', [
                    'model' => get_class($model),
                    'attributes' => $model->getAttributes()
                ]);
            });

            $model::updating(function ($model) {
                \Log::info('Model updating', [
                    'model' => get_class($model),
                    'changes' => $model->getDirty(),
                    'original' => $model->getOriginal()
                ]);
            });

            $model::deleting(function ($model) {
                \Log::info('Model deleting', [
                    'model' => get_class($model),
                    'id' => $model->getKey(),
                    'attributes' => $model->getAttributes()
                ]);
            });
        }
    }

    #endregion
}