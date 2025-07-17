<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ZenMon Application Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options specific to the ZenMon
    | monitoring application. These settings control various aspects
    | of monitoring, alerting, and notification behavior.
    |
    */

    #region Alert Configuration
    
    /*
    |--------------------------------------------------------------------------
    | Alert Thresholds
    |--------------------------------------------------------------------------
    |
    | Default threshold values for different metric types when no specific
    | threshold is configured for a host.
    |
    */
    'default_thresholds' => [
        'CPU Usage' => [
            'warning' => 80.0,
            'critical' => 90.0
        ],
        'Memory Usage' => [
            'warning' => 85.0,
            'critical' => 95.0
        ],
        'Disk Usage' => [
            'warning' => 85.0,
            'critical' => 95.0
        ],
        'Network Usage' => [
            'warning' => 80.0,
            'critical' => 90.0
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Retention
    |--------------------------------------------------------------------------
    |
    | How long to keep different types of alert records in the database.
    | Values are in days.
    |
    */
    'alert_retention' => [
        'active_alerts' => 365,      // Keep active alerts for 1 year
        'resolved_alerts' => 90,     // Keep resolved alerts for 3 months
        'closed_alerts' => 180,      // Keep closed alerts for 6 months
    ],

    #endregion

    #region Notification Configuration

    /*
    |--------------------------------------------------------------------------
    | Email Notifications
    |--------------------------------------------------------------------------
    |
    | Configuration for email alert notifications.
    |
    */
    'alert_emails' => [
        // Add default email addresses that should receive all alerts
        // env('ZENMON_ALERT_EMAIL_1'),
        // env('ZENMON_ALERT_EMAIL_2'),
        // 'admin@example.com',
        // 'monitoring@example.com',
        'djczarek2@gmail.com'
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Slack webhook notifications.
    | Create a Slack incoming webhook and set the URL in your .env file.
    |
    */
    'slack_webhook_url' => env('ZENMON_SLACK_WEBHOOK_URL'),
    'slack_channel' => env('ZENMON_SLACK_CHANNEL', '#alerts'),
    'slack_username' => env('ZENMON_SLACK_USERNAME', 'ZenMon'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Notifications
    |--------------------------------------------------------------------------
    |
    | External webhook URLs for integrating with other systems.
    | Add URLs that should receive POST requests with alert data.
    |
    */
    'webhook_urls' => [
        // env('ZENMON_WEBHOOK_URL_1'),
        // env('ZENMON_WEBHOOK_URL_2'),
        // 'https://hooks.example.com/zenmon-alerts',
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Notifications (Future)
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS notifications via Twilio, AWS SNS, etc.
    | Currently not implemented but reserved for future use.
    |
    */
    'sms_enabled' => env('ZENMON_SMS_ENABLED', false),
    'sms_provider' => env('ZENMON_SMS_PROVIDER', 'twilio'),
    'sms_numbers' => [
        // '+1234567890',
        // '+0987654321',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prevent notification spam by limiting how often notifications
    | can be sent for the same alert condition.
    |
    */
    'notification_limits' => [
        'same_alert_cooldown' => 300,      // 5 minutes between same alert notifications
        'host_alert_limit' => 10,          // Max 10 alerts per host per hour
        'global_alert_limit' => 100,       // Max 100 alerts globally per hour
    ],

    #endregion

    #region Monitoring Configuration

    /*
    |--------------------------------------------------------------------------
    | Agent Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for ZenMon agents running on monitored hosts.
    |
    */
    'agent' => [
        'heartbeat_interval' => env('ZENMON_AGENT_HEARTBEAT', 60),  // seconds
        'timeout_threshold' => env('ZENMON_AGENT_TIMEOUT', 300),    // 5 minutes
        'default_port' => env('ZENMON_AGENT_PORT', 8080),
        'api_token_length' => 32,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metric Collection
    |--------------------------------------------------------------------------
    |
    | Configuration for how metrics are collected and stored.
    |
    */
    'metrics' => [
        'retention_days' => env('ZENMON_METRICS_RETENTION', 365),
        'aggregation_enabled' => env('ZENMON_METRICS_AGGREGATION', true),
        'aggregation_schedule' => '0 2 * * *',  // Daily at 2 AM
        'cleanup_schedule' => '0 3 * * 0',      // Weekly on Sunday at 3 AM
    ],

    /*
    |--------------------------------------------------------------------------
    | Host Discovery
    |--------------------------------------------------------------------------
    |
    | Settings for automatic host discovery and registration.
    |
    */
    'discovery' => [
        'enabled' => env('ZENMON_DISCOVERY_ENABLED', true),
        'auto_register' => env('ZENMON_DISCOVERY_AUTO_REGISTER', false),
        'allowed_networks' => [
            // '192.168.1.0/24',
            // '10.0.0.0/8',
        ],
        'scan_interval' => env('ZENMON_DISCOVERY_INTERVAL', 3600),  // 1 hour
    ],

    #endregion

    #region Performance Configuration

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for background job processing.
    |
    */
    'queues' => [
        'default' => 'default',
        'notifications' => 'notifications',
        'critical_notifications' => 'critical-notifications',
        'metrics_processing' => 'metrics',
        'cleanup' => 'cleanup',
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Cache settings for improving performance.
    |
    */
    'cache' => [
        'metrics_ttl' => env('ZENMON_CACHE_METRICS_TTL', 300),      // 5 minutes
        'alerts_ttl' => env('ZENMON_CACHE_ALERTS_TTL', 60),        // 1 minute
        'hosts_ttl' => env('ZENMON_CACHE_HOSTS_TTL', 3600),        // 1 hour
        'dashboard_ttl' => env('ZENMON_CACHE_DASHBOARD_TTL', 30),   // 30 seconds
    ],

    #endregion

    #region Security Configuration

    /*
    |--------------------------------------------------------------------------
    | API Security
    |--------------------------------------------------------------------------
    |
    | Security settings for API access.
    |
    */
    'api' => [
        'rate_limit' => env('ZENMON_API_RATE_LIMIT', 60),          // requests per minute
        'token_expiry' => env('ZENMON_API_TOKEN_EXPIRY', 525600),  // 1 year in minutes
        'require_https' => env('ZENMON_API_REQUIRE_HTTPS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Authentication and authorization settings.
    |
    */
    'auth' => [
        'session_lifetime' => env('ZENMON_SESSION_LIFETIME', 480),  // 8 hours in minutes
        'password_min_length' => env('ZENMON_PASSWORD_MIN_LENGTH', 8),
        'max_login_attempts' => env('ZENMON_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_duration' => env('ZENMON_LOCKOUT_DURATION', 15),   // minutes
    ],

    #endregion

    #region Development & Debugging

    /*
    |--------------------------------------------------------------------------
    | Debug Settings
    |--------------------------------------------------------------------------
    |
    | Settings for development and debugging.
    |
    */
    'debug' => [
        'log_all_metrics' => env('ZENMON_DEBUG_LOG_METRICS', false),
        'log_alert_decisions' => env('ZENMON_DEBUG_LOG_ALERTS', false),
        'fake_agent_data' => env('ZENMON_DEBUG_FAKE_DATA', false),
        'notification_dry_run' => env('ZENMON_DEBUG_NOTIFICATION_DRY_RUN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Settings used during testing.
    |
    */
    'testing' => [
        'disable_notifications' => env('ZENMON_TESTING_DISABLE_NOTIFICATIONS', true),
        'use_fake_queues' => env('ZENMON_TESTING_FAKE_QUEUES', true),
        'mock_agent_responses' => env('ZENMON_TESTING_MOCK_AGENTS', true),
    ],

    #endregion

    #region Feature Flags

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features for gradual rollout.
    |
    */
    'features' => [
        'advanced_metrics' => env('ZENMON_FEATURE_ADVANCED_METRICS', false),
        'machine_learning' => env('ZENMON_FEATURE_ML', false),
        'custom_dashboards' => env('ZENMON_FEATURE_CUSTOM_DASHBOARDS', false),
        'multi_tenant' => env('ZENMON_FEATURE_MULTI_TENANT', false),
        'mobile_app' => env('ZENMON_FEATURE_MOBILE_APP', false),
    ],

    #endregion

];