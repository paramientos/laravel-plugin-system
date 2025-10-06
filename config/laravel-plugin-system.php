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
        | Health Check Interval
        |--------------------------------------------------------------------------
        |
        | How often to run health checks (in minutes).
        |
        */
        'check_interval' => env('PLUGIN_HEALTH_CHECK_INTERVAL', 5),

        /*
        |--------------------------------------------------------------------------
        | Metrics Retention
        |--------------------------------------------------------------------------
        |
        | How long to keep health metrics (in days).
        |
        */
        'metrics_retention_days' => env('PLUGIN_HEALTH_METRICS_RETENTION', 30),

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

        /*
        |--------------------------------------------------------------------------
        | Auto Recovery
        |--------------------------------------------------------------------------
        |
        | Automatic recovery settings for failed plugins.
        |
        */
        'auto_recovery' => [
            'enabled' => env('PLUGIN_HEALTH_AUTO_RECOVERY', false),
            'max_attempts' => env('PLUGIN_HEALTH_RECOVERY_ATTEMPTS', 3),
            'retry_delay_minutes' => env('PLUGIN_HEALTH_RETRY_DELAY', 5),
        ],
    ],
];
