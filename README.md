# Laravel Plugin System

An extensive Laravel plugin system that provides automatic registration of routes, controllers, services, views, and configurations for modular application development.

## Features

-  **Automatic Plugin Discovery** - Automatically scans and registers plugins
- ️ **Route Registration** - Auto-registers plugin routes with customizable prefixes
-  **Controller Binding** - Automatically binds plugin controllers to the service container
-  **Service Registration** - Registers services as singletons with interface binding support
-  **View Integration** - Seamless integration with Laravel views and Livewire Volt
- ️ **Config Management** - Automatic configuration loading and merging
-  **Plugin Generator** - Artisan command to create new plugins with boilerplate code
-  **Health Monitoring** - Real-time plugin health monitoring with metrics, alerts, and automatic recovery
-  **Performance Tracking** - Monitor memory usage, execution time, error rates, and database queries
-  **Alert System** - Multi-channel alerts (log, email, Slack) for plugin issues
-  **Health Commands** - Artisan commands for health checks and error management
- 🔧 **Debug & Profiling Tools** - Comprehensive debugging tools for plugin development
- 📊 **Performance Analysis** - Advanced profiling with bottleneck detection and recommendations
- 📋 **Log Management** - Plugin-specific log filtering, tailing, and analysis
- 🔍 **Request Debugging** - Middleware for request/response debugging and performance tracking

## Installation

Install the package via Composer:

```bash
composer require soysaltan/laravel-plugin-system
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-plugin-system-config
```

## Configuration

The configuration file `config/laravel-plugin-system.php` allows you to customize:

```php
return [
    // Path where plugins are stored
    'plugins_path' => app_path('Plugins'),
    
    // Base namespace for plugins
    'plugin_namespace' => 'App\\Plugins',
    
    // Whether to prefix routes with 'plugins/'
    'use_plugins_prefix_in_routes' => false,
    
    // Default view type for new plugins
    'default_view_type' => 'volt', // 'volt' or 'blade'
    
    // Enable/disable Volt support
    'enable_volt_support' => true,
    
    // Health monitoring configuration
    'health_monitoring' => [
        'enabled' => true,
        'collect_metrics' => true,
        'store_errors' => true,
        'max_errors_to_store' => 100,
        
        'thresholds' => [
            'memory_usage' => 128 * 1024 * 1024, // 128MB
            'execution_time' => 5000, // 5 seconds
            'error_rate' => 0.05, // 5%
            'critical_error_threshold' => 10,
        ],
        
        'alerts' => [
            'enabled' => true,
            'channels' => ['log', 'email'],
            'email' => [
                'to' => 'admin@example.com',
                'subject' => 'Plugin Health Alert',
            ],
            'slack' => [
                'webhook_url' => env('SLACK_WEBHOOK_URL'),
                'channel' => '#alerts',
            ],
        ],
    ],
];
```

## Usage

### Creating a Plugin

Use the Artisan command to create a new plugin:

```bash
# Create plugin with default view type (configured in config)
php artisan make:plugin MyAwesomePlugin

# Create plugin with Volt views
php artisan make:plugin MyAwesomePlugin --view-type=volt

# Create plugin with traditional Blade views
php artisan make:plugin MyAwesomePlugin --view-type=blade

# Auto-detect best view type based on configuration and availability
php artisan make:plugin MyAwesomePlugin --view-type=auto
```

This creates the following structure:

```
app/Plugins/MyAwesomePlugin/
├── config.php                          # Plugin configuration
├── routes.php                          # Plugin routes
├── Controllers/
│   └── MyAwesomePluginController.php   # Plugin controller
├── Services/
│   ├── MyAwesomePluginService.php      # Plugin service
│   └── MyAwesomePluginServiceInterface.php # Service interface
└── Views/
    └── index.blade.php                 # Livewire Volt component
```

### Plugin Structure

#### Config File (`config.php`)
```php
<?php
return [
    'name' => 'MyAwesomePlugin',
    'version' => '1.0.0',
    'description' => 'MyAwesomePlugin plugin',
    'enabled' => true,
];
```

#### Routes File (`routes.php`)
```php
<?php
use Livewire\Volt\Volt;

Volt::route('/', 'index');
```

#### Controller
```php
<?php
namespace App\Plugins\MyAwesomePlugin\Controllers;

use App\Http\Controllers\Controller;

class MyAwesomePluginController extends Controller
{
    public function index()
    {
        // Controller logic
    }
}
```

#### Service & Interface
```php
<?php
namespace App\Plugins\MyAwesomePlugin\Services;

interface MyAwesomePluginServiceInterface
{
    public function handle(): array;
}

class MyAwesomePluginService implements MyAwesomePluginServiceInterface
{
    public function handle(): array
    {
        return [
            'message' => 'MyAwesomePlugin service is working!',
            'timestamp' => now()->toISOString(),
        ];
    }
}
```

#### Views

The plugin system supports both **Livewire Volt** and **traditional Blade** views:

**Volt Component (default):**
```php
<?php
new class extends \Livewire\Volt\Component
{
    public string $message = 'Welcome to MyAwesomePlugin Plugin!';

    public function mount(): void
    {
        $this->message = 'Hello from MyAwesomePlugin!';
    }
}
?>

<div class="p-6 bg-white rounded-lg shadow-md">
    <h1 class="text-2xl font-bold text-gray-800 mb-4">MyAwesomePlugin Plugin</h1>
    <p class="text-gray-600 mb-4">{{ $message }}</p>
</div>
```

**Traditional Blade View:**
```blade
@extends('layouts.app')

@section('content')
<div class="p-6 bg-white rounded-lg shadow-md">
    <h1 class="text-2xl font-bold text-gray-800 mb-4">MyAwesomePlugin Plugin</h1>
    <p class="text-gray-600 mb-4">Welcome to MyAwesomePlugin Plugin!</p>
    
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h2 class="text-lg font-semibold text-blue-800 mb-2">Plugin Information</h2>
        <ul class="text-blue-700 space-y-1">
            <li><strong>View Type:</strong> Traditional Blade</li>
        </ul>
    </div>
</div>
@endsection
```

### Accessing Plugins

Once created, your plugin will be automatically registered and accessible via:

- **Routes**: `http://your-app.com/myawesomeplugin/` (or `/plugins/myawesomeplugin/` if prefix is enabled)
- **Config**: `config('MyAwesomePlugin.name')`
- **Services**: Injected via dependency injection or `app(MyAwesomePluginServiceInterface::class)`

### Service Injection

Plugin services are automatically registered and can be injected:

```php
class SomeController extends Controller
{
    public function __construct(
        private MyAwesomePluginServiceInterface $pluginService
    ) {}

    public function index()
    {
        $result = $this->pluginService->handle();
        return response()->json($result);
    }
}
```

## Advanced Configuration

### Custom Plugin Path

You can change the default plugin path in the configuration:

```php
'plugins_path' => base_path('custom/plugins'),
```

### Custom Namespace

Change the base namespace for plugins:

```php
'plugin_namespace' => 'Custom\\Plugins',
```

### Route Prefixing

Enable route prefixing to add 'plugins/' to all plugin routes:

```php
'use_plugins_prefix_in_routes' => true,
```

## Plugin Health Monitoring

The plugin system includes comprehensive health monitoring capabilities to track plugin performance, detect issues, and provide automated recovery options.

### Health Monitoring Commands

#### Check Plugin Health

Display health status for all plugins:

```bash
php artisan plugin:health
```

Check specific plugin health:

```bash
php artisan plugin:health MyAwesomePlugin
```

Display detailed health information:

```bash
php artisan plugin:health --detailed or php artisan plugin:health MyAwesomePlugin --detailed
```

Output health data in JSON format:

```bash
php artisan plugin:health --json
```

Show only plugins with errors:

```bash
php artisan plugin:health --errors-only
```

Enable watch mode for continuous monitoring:

```bash
php artisan plugin:health --watch or php artisan plugin:health MyAwesomePlugin --watch
```

#### Clear Plugin Errors

Clear errors for all plugins:

```bash
php artisan plugin:health --clear-errors
```

Clear errors for specific plugin:

```bash
php artisan plugin:health MyAwesomePlugin --clear-errors
```

## Plugin Debugging & Profiling Tools

The plugin system includes comprehensive debugging and profiling tools to help developers analyze plugin performance, troubleshoot issues, and optimize their code.

### Debug Commands

#### Plugin Debug Command

Monitor plugin execution with detailed debugging information:

```bash
# Debug all plugins
php artisan plugin:debug

# Debug specific plugin
php artisan plugin:debug MyAwesomePlugin

# Enable trace mode for detailed execution flow
php artisan plugin:debug --trace

# Monitor memory usage
php artisan plugin:debug --memory

# Track database queries
php artisan plugin:debug --queries

# Monitor slow queries (over 100ms)
php artisan plugin:debug --slow-queries

# Watch mode for continuous monitoring
php artisan plugin:debug --watch

# Output in JSON format
php artisan plugin:debug --format=json

# Detailed output with all information
php artisan plugin:debug --format=detailed
```

#### Plugin Log Viewer

View and manage plugin-specific logs:

```bash
# View logs for specific plugin
php artisan plugin:logs MyAwesomePlugin

# Filter by log level
php artisan plugin:logs MyAwesomePlugin --level=error

# Tail logs in real-time
php artisan plugin:logs MyAwesomePlugin --tail

# Limit number of lines
php artisan plugin:logs MyAwesomePlugin --lines=50

# Filter by date
php artisan plugin:logs MyAwesomePlugin --since="2024-01-01"

# Search for specific text
php artisan plugin:logs MyAwesomePlugin --search="database error"

# Export logs to file
php artisan plugin:logs MyAwesomePlugin --export=logs.txt

# Clear plugin logs
php artisan plugin:logs MyAwesomePlugin --clear

# Output in different formats
php artisan plugin:logs MyAwesomePlugin --format=json
```

### Profiling Features

#### Performance Analysis

The profiling service provides detailed performance analysis:

- **Execution Time Tracking** - Monitor method execution times
- **Memory Usage Analysis** - Track memory consumption patterns
- **Database Query Profiling** - Analyze query performance and N+1 issues
- **Bottleneck Detection** - Identify performance bottlenecks automatically
- **Performance Recommendations** - Get actionable optimization suggestions
- **Plugin Comparison** - Compare performance across different plugins
- **Anomaly Detection** - Detect unusual performance patterns

#### Debug Middleware

Enable request-level debugging by adding the middleware to your routes:

```php
Route::middleware(['plugin.debug'])->group(function () {
    // Your plugin routes
});
```

The middleware provides:

- **Request/Response Logging** - Complete request and response data
- **Performance Metrics** - Execution time and memory usage per request
- **Query Tracking** - Database queries executed during request
- **Debug Headers** - Performance data in response headers
- **Error Tracking** - Automatic error logging and analysis

### Debugging Configuration

Configure debugging tools in `config/laravel-plugin-system.php`:

```php
'debugging' => [
    'enabled' => true,
    'profiling' => [
        'enabled' => true,
        'memory_tracking' => true,
        'query_tracking' => true,
        'slow_query_threshold' => 100, // milliseconds
    ],
    'logging' => [
        'enabled' => true,
        'log_requests' => true,
        'log_responses' => true,
        'sanitize_sensitive_data' => true,
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'max_entries' => 1000,
    ],
],
```

### Health Monitoring Features

#### Automatic Metrics Collection

The system automatically collects the following metrics:

- **Memory Usage** - Peak memory consumption during plugin execution
- **Execution Time** - Time taken for plugin operations
- **Database Queries** - Number of database queries executed
- **Error Rate** - Frequency of errors and exceptions
- **Request Count** - Number of requests processed
- **Response Time** - Average response time for plugin endpoints

#### Health Status Indicators

Each plugin receives a health status based on configurable thresholds:

- 🟢 **Healthy** - All metrics within normal ranges
- 🟡 **Warning** - Some metrics approaching thresholds
- 🔴 **Critical** - Metrics exceeding thresholds or critical errors present

#### Alert System

The monitoring system provides multi-channel alerts:

- **Log Alerts** - Detailed logs for all health events
- **Email Notifications** - Email alerts for critical issues
- **Slack Integration** - Real-time Slack notifications
- **Custom Channels** - Extensible alert system for custom integrations

### Health Monitoring Configuration

Configure health monitoring in `config/laravel-plugin-system.php`:

```php
'health_monitoring' => [
    'enabled' => true,
    'collect_metrics' => true,
    'store_errors' => true,
    'max_errors_to_store' => 100,
    
    'thresholds' => [
        'memory_usage' => 128 * 1024 * 1024, // 128MB
        'execution_time' => 5000, // 5 seconds
        'error_rate' => 0.05, // 5%
        'critical_error_threshold' => 10,
    ],
    
    'alerts' => [
        'enabled' => true,
        'channels' => ['log', 'email'],
        'email' => [
            'to' => 'admin@example.com',
            'subject' => 'Plugin Health Alert',
        ],
        'slack' => [
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
            'channel' => '#alerts',
        ],
    ],
],
```

## Requirements

- PHP 8.1+
- Laravel 10.0+ or 11.0+
- Livewire Volt 1.0+ (for view components)

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

If you discover any security vulnerabilities or bugs, please send an e-mail to soysaltan@hotmail.it
