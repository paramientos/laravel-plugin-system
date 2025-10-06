<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plugins Path
    |--------------------------------------------------------------------------
    |
    | This value determines the path where your plugins are stored.
    | By default, plugins are stored in the app/Plugins directory.
    |
    */
    'plugins_path' => app_path('Plugins'),

    /*
    |--------------------------------------------------------------------------
    | Plugin Namespace
    |--------------------------------------------------------------------------
    |
    | This value determines the base namespace for your plugins.
    | All plugin classes will be created under this namespace.
    |
    */
    'plugin_namespace' => 'App\\Plugins',

    /*
    |--------------------------------------------------------------------------
    | Use Plugins Prefix in Routes
    |--------------------------------------------------------------------------
    |
    | When set to true, all plugin routes will be prefixed with 'plugins/'.
    | When false, routes will only use the plugin name as prefix.
    |
    */
    'use_plugins_prefix_in_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Default View Type
    |--------------------------------------------------------------------------
    |
    | This determines the default view type when creating new plugins.
    | Options: 'volt', 'blade'
    | 'volt' - Creates Livewire Volt components
    | 'blade' - Creates traditional Blade views
    |
    */
    'default_view_type' => 'volt',

    /*
    |--------------------------------------------------------------------------
    | Volt Support
    |--------------------------------------------------------------------------
    |
    | Whether to enable Volt support for plugin views.
    | Set to false if you don't want to use Livewire Volt.
    |
    */
    'enable_volt_support' => true,

    /*
    |--------------------------------------------------------------------------
    | Health Monitoring
    |--------------------------------------------------------------------------
    |
    | Configuration for plugin health monitoring system.
    |
    */
    'health_monitoring' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Health Monitoring
        |--------------------------------------------------------------------------
        |
        | Whether to enable the plugin health monitoring system.
        |
        */
        'enabled' => env('PLUGIN_HEALTH_MONITORING_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Health Thresholds
        |--------------------------------------------------------------------------
        |
        | Thresholds for determining plugin health status.
        |
        */
        'thresholds' => [
            'memory_usage_mb' => env('PLUGIN_HEALTH_MEMORY_THRESHOLD', 100),
            'response_time_ms' => env('PLUGIN_HEALTH_RESPONSE_TIME_THRESHOLD', 5000),
            'error_rate_percent' => env('PLUGIN_HEALTH_ERROR_RATE_THRESHOLD', 5),
            'uptime_percent' => env('PLUGIN_HEALTH_UPTIME_THRESHOLD', 95),
            'cpu_usage_percent' => env('PLUGIN_HEALTH_CPU_THRESHOLD', 80),
        ],

        /*
        |--------------------------------------------------------------------------
        | Alert System
        |--------------------------------------------------------------------------
        |
        | Configuration for health monitoring alerts.
        |
        */
        'alerts' => [
            'enabled' => env('PLUGIN_HEALTH_ALERTS_ENABLED', true),
            'channels' => ['log'], // Available: log, email, slack, webhook
            'cooldown_minutes' => env('PLUGIN_HEALTH_ALERT_COOLDOWN', 60),
        ],

        /*
        |--------------------------------------------------------------------------
        | Alert Thresholds
        |--------------------------------------------------------------------------
        |
        | Thresholds for triggering alerts.
        |
        */
        'alert_thresholds' => [
            'critical_error_count' => env('PLUGIN_HEALTH_CRITICAL_ERROR_COUNT', 5),
            'warning_error_count' => env('PLUGIN_HEALTH_WARNING_ERROR_COUNT', 10),
            'memory_threshold' => env('PLUGIN_HEALTH_ALERT_MEMORY_THRESHOLD', 100 * 1024 * 1024), // 100MB
            'response_time_threshold' => env('PLUGIN_HEALTH_ALERT_RESPONSE_TIME', 5000), // 5 seconds
            'uptime_threshold' => env('PLUGIN_HEALTH_ALERT_UPTIME', 95), // 95%
        ],

        /*
        |--------------------------------------------------------------------------
        | Email Notifications
        |--------------------------------------------------------------------------
        |
        | Email settings for health monitoring alerts.
        |
        */
        'email_recipients' => env('PLUGIN_HEALTH_EMAIL_RECIPIENTS', ''),

        /*
        |--------------------------------------------------------------------------
        | Slack Integration
        |--------------------------------------------------------------------------
        |
        | Slack webhook URL for health monitoring alerts.
        |
        */
        'slack_webhook_url' => env('PLUGIN_HEALTH_SLACK_WEBHOOK', ''),

        /*
        |--------------------------------------------------------------------------
        | Custom Webhook
        |--------------------------------------------------------------------------
        |
        | Custom webhook URL for health monitoring alerts.
        |
        */
        'webhook_url' => env('PLUGIN_HEALTH_WEBHOOK_URL', ''),

        /*
        |--------------------------------------------------------------------------
        | Storage Path
        |--------------------------------------------------------------------------
        |
        | Path where health monitoring data will be stored.
        |
        */
        'storage_path' => storage_path('app/plugin-health'),

        /*
        |--------------------------------------------------------------------------
        | Cache Settings
        |--------------------------------------------------------------------------
        |
        | Cache configuration for health monitoring.
        |
        */
        'cache' => [
            'prefix' => 'plugin_health_',
            'ttl' => env('PLUGIN_HEALTH_CACHE_TTL', 3600), // 1 hour
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for plugin debugging and profiling tools.
    |
    */
    'debug' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Debug Mode
        |--------------------------------------------------------------------------
        |
        | Whether to enable plugin debugging features.
        | This should be disabled in production.
        |
        */
        'enabled' => env('PLUGIN_DEBUG_ENABLED', env('APP_DEBUG', false)),

        /*
        |--------------------------------------------------------------------------
        | Debug Routes
        |--------------------------------------------------------------------------
        |
        | Specific routes that should trigger debug middleware.
        | Leave empty to debug all routes when debug headers/params are present.
        |
        */
        'routes' => [
            // 'plugin.example.index',
            // 'plugin.*.show',
        ],

        /*
        |--------------------------------------------------------------------------
        | Profiling Configuration
        |--------------------------------------------------------------------------
        |
        | Settings for plugin profiling and performance analysis.
        |
        */
        'profiling' => [
            'enabled' => env('PLUGIN_PROFILING_ENABLED', true),
            'memory_tracking' => env('PLUGIN_PROFILING_MEMORY', true),
            'query_tracking' => env('PLUGIN_PROFILING_QUERIES', true),
            'slow_query_threshold' => env('PLUGIN_SLOW_QUERY_THRESHOLD', 100), // milliseconds
        ],

        /*
        |--------------------------------------------------------------------------
        | Logging Configuration
        |--------------------------------------------------------------------------
        |
        | Settings for debug logging.
        |
        */
        'logging' => [
            'enabled' => env('PLUGIN_DEBUG_LOGGING', true),
            'log_channel' => env('PLUGIN_DEBUG_LOG_CHANNEL', 'single'),
            'log_requests' => env('PLUGIN_DEBUG_LOG_REQUESTS', true),
            'log_responses' => env('PLUGIN_DEBUG_LOG_RESPONSES', true),
            'sanitize_sensitive_data' => env('PLUGIN_DEBUG_SANITIZE', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Cache Configuration
        |--------------------------------------------------------------------------
        |
        | Settings for debug data caching.
        |
        */
        'cache' => [
            'enabled' => env('PLUGIN_DEBUG_CACHE', true),
            'cache_ttl' => env('PLUGIN_DEBUG_CACHE_TTL', 3600), // 1 hour
            'max_history' => env('PLUGIN_DEBUG_MAX_HISTORY', 100),
            'max_entries' => env('PLUGIN_DEBUG_MAX_ENTRIES', 1000),
        ],
    ],
];
