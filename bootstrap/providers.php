<?php

/**
 * ZenMon Application Providers Configuration
 * 
 * This file defines all service providers that should be loaded
 * by the Laravel application during the bootstrap process.
 * 
 * Service providers are responsible for:
 * - Registering services into the service container
 * - Bootstrapping application components
 * - Configuring third-party packages
 * - Setting up application-wide behavior
 */

// return [
//     App\Providers\AppServiceProvider::class,
//     App\Providers\TelescopeServiceProvider::class,
// ];

return [
    
    #region Core Laravel Providers
    
    /// <summary>
    /// Core framework service providers
    /// These providers handle fundamental Laravel functionality
    /// </summary>
    App\Providers\AppServiceProvider::class,
    
    #endregion
    
    #region Development & Debugging Providers
    
    /// <summary>
    /// Development and debugging service providers
    /// Essential tools for ZenMon development and troubleshooting
    /// </summary>
    // Laravel Telescope for application monitoring and debugging
    // Provides detailed insights into requests, queries, jobs, etc.
    App\Providers\TelescopeServiceProvider::class,
    
    // Tinker for artisan console enhancements
    Laravel\Tinker\TinkerServiceProvider::class,
    
    #endregion
    
    #region Authentication & Authorization Providers
    
    /// <summary>
    /// Authentication and authorization service providers
    /// Handle user authentication, API tokens, and permissions
    /// </summary>
    // Laravel Sanctum for API authentication
    Laravel\Sanctum\SanctumServiceProvider::class,
    
    #endregion
    
    #region API Documentation Providers
    
    /// <summary>
    /// API documentation and development tools
    /// </summary>
    // L5-Swagger for API documentation generation
    L5Swagger\L5SwaggerServiceProvider::class,
    
    #endregion
    
    #region Third-Party Package Providers
    
    /// <summary>
    /// Third-party package service providers
    /// External packages that extend application functionality
    /// </summary>
    // Spatie Query Builder for advanced API filtering
    Spatie\QueryBuilder\QueryBuilderServiceProvider::class,
    
    #endregion
    
    #region Monitoring & Observability Providers
    
    /// <summary>
    /// Monitoring and observability service providers
    /// Custom providers for ZenMon monitoring functionality
    /// </summary>
    // Add custom monitoring providers here as they are developed
    // App\Providers\MonitoringServiceProvider::class,
    // App\Providers\AlertingServiceProvider::class,
    // App\Providers\MetricsCollectionServiceProvider::class,
    
    #endregion
    
    #region Future Extension Points
    
    /// <summary>
    /// Reserved space for future service providers
    /// These can be uncommented and configured as needed:
    /// 
    /// Queue Processing:
    /// - App\Providers\QueueServiceProvider::class
    /// 
    /// Notification System:
    /// - App\Providers\NotificationServiceProvider::class
    /// 
    /// Caching & Performance:
    /// - App\Providers\CacheServiceProvider::class
    /// 
    /// Reporting & Analytics:
    /// - App\Providers\ReportingServiceProvider::class
    /// 
    /// Integration Services:
    /// - App\Providers\IntegrationServiceProvider::class
    /// 
    /// Security & Compliance:
    /// - App\Providers\SecurityServiceProvider::class
    /// </summary>
    
    #endregion
    
];