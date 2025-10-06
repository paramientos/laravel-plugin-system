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

## Requirements

- PHP 8.1+
- Laravel 10.0+ or 11.0+
- Livewire Volt 1.0+ (for view components)

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

If you discover any security vulnerabilities or bugs, please send an e-mail to the maintainer.
