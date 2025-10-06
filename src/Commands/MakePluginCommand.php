<?php

namespace SoysalTan\LaravelPluginSystem\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SoysalTan\LaravelPluginSystem\PluginManager;
use Symfony\Component\Console\Command\Command as CommandAlias;

class MakePluginCommand extends Command
{
    protected $signature = 'make:plugin {name : The name of the plugin} {--view-type=auto : View type (volt, blade, auto)}';

    protected $description = 'Create a new plugin with all necessary directories and files';

    public function handle()
    {
        $pluginName = $this->argument('name');
        $pluginName = Str::studly($pluginName);

        $pluginsPath = config('laravel-plugin-system.plugins_path', app_path('Plugins'));
        $pluginPath = $pluginsPath."/{$pluginName}";

        if (File::exists($pluginPath)) {
            $this->error("Plugin '{$pluginName}' already exists!");

            return CommandAlias::FAILURE;
        }

        // Determine view type
        $viewType = $this->determineViewType();

        $this->info("Creating plugin: {$pluginName}");
        $this->info("View type: {$viewType}");

        $this->createPluginDirectories($pluginPath);
        $this->createPluginFiles($pluginPath, $pluginName, $viewType);

        $this->info("Plugin '{$pluginName}' created successfully!");
        $this->info("Plugin location: {$pluginPath}");
        $this->info("Plugin is enabled by default. You can disable it in the {$pluginPath}/config.php file.");
        $this->info("Plugin routes are registered in the {$pluginPath}/routes.php file.");

        $pluginNameLower = strtolower($pluginName);
        $prefix = PluginManager::$usePluginsPrefixInRoutes ? 'plugins' : '';
        $url = url("{$prefix}/{$pluginNameLower}");
        $this->info("You can access to index view page via url {$url}");

        return CommandAlias::SUCCESS;
    }

    protected function createPluginDirectories(string $pluginPath): void
    {
        $directories = [
            $pluginPath,
            $pluginPath.'/Controllers',
            $pluginPath.'/Services',
            $pluginPath.'/Views',
            $pluginPath.'/Commands',
            $pluginPath.'/Events',
            $pluginPath.'/Listeners',
            $pluginPath.'/Enums',
            $pluginPath.'/Concerns',
        ];

        foreach ($directories as $directory) {
            File::makeDirectory($directory, 0755, true);
            $this->line('Created directory: '.basename($directory));
        }
    }

    protected function determineViewType(): string
    {
        $viewType = $this->option('view-type');

        if ($viewType === 'auto') {
            $defaultType = config('laravel-plugin-system.default_view_type', 'volt');
            $voltEnabled = config('laravel-plugin-system.enable_volt_support', true);
            $voltExists = class_exists('Livewire\Volt\Volt');

            if ($defaultType === 'volt' && $voltEnabled && $voltExists) {
                return 'volt';
            }

            return 'blade';
        }

        if (!in_array($viewType, ['volt', 'blade'])) {
            $this->error("Invalid view type '{$viewType}'. Available options: volt, blade, auto");
            exit(1);
        }

        if ($viewType === 'volt') {
            $voltEnabled = config('laravel-plugin-system.enable_volt_support', true);
            $voltExists = class_exists('Livewire\Volt\Volt');

            if (!$voltEnabled || !$voltExists) {
                $this->error('Volt is not available. Please install Livewire Volt or use --view-type=blade');
                exit(1);
            }
        }

        return $viewType;
    }

    protected function createPluginFiles(string $pluginPath, string $pluginName, string $viewType): void
    {
        $this->createConfigFile($pluginPath, $pluginName);
        $this->createRoutesFile($pluginPath, $pluginName, $viewType);
        $this->createControllerFile($pluginPath, $pluginName);
        $this->createServiceFile($pluginPath, $pluginName);
        $this->createViewFile($pluginPath, $pluginName, $viewType);
        $this->createCommandFile($pluginPath, $pluginName);
        $this->createEventFile($pluginPath, $pluginName);
        $this->createListenerFile($pluginPath, $pluginName);
        $this->createEnumFile($pluginPath, $pluginName);
        $this->createConcernFile($pluginPath, $pluginName);
    }

    protected function createConfigFile(string $pluginPath, string $pluginName): void
    {
        $content = "<?php

return [
    'name' => '{$pluginName}',
    'version' => '1.0.0',
    'description' => '{$pluginName} plugin',
    'enabled' => true,
];
";
        File::put($pluginPath.'/config.php', $content);
        $this->line('Created: config.php');
    }

    protected function createRoutesFile(string $pluginPath, string $pluginName, string $viewType): void
    {
        if ($viewType === 'volt') {
            $content = "<?php

use Livewire\Volt\Volt;

Volt::route('/', 'index');
";
        } else {
            $pluginNameLower = strtolower($pluginName);
            $content = "<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('plugins.{$pluginNameLower}::index');
});
";
        }

        File::put($pluginPath.'/routes.php', $content);
        $this->line('Created: routes.php');
    }

    protected function createControllerFile(string $pluginPath, string $pluginName): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');
        $content = "<?php

namespace {$namespace}\\{$pluginName}\\Controllers;

use App\\Http\\Controllers\\Controller;

class {$pluginName}Controller extends Controller
{
    public function index()
    {
        //
    }
}
";
        File::put($pluginPath.'/Controllers/'.$pluginName.'Controller.php', $content);
        $this->line("Created: {$pluginName}Controller.php");
    }

    protected function createServiceFile(string $pluginPath, string $pluginName): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');

        $interfaceContent = "<?php

namespace {$namespace}\\{$pluginName}\\Services;

interface {$pluginName}ServiceInterface
{
    public function handle(): array;
}
";

        $serviceContent = "<?php

namespace {$namespace}\\{$pluginName}\\Services;

class {$pluginName}Service implements {$pluginName}ServiceInterface
{
    public function handle(): array
    {
        return [
            'message' => '{$pluginName} service is working!',
            'timestamp' => now()->toISOString(),
        ];
    }
}
";

        File::put($pluginPath.'/Services/'.$pluginName.'ServiceInterface.php', $interfaceContent);
        File::put($pluginPath.'/Services/'.$pluginName.'Service.php', $serviceContent);

        $this->line("Created: {$pluginName}ServiceInterface.php");
        $this->line("Created: {$pluginName}Service.php");
    }

    protected function createViewFile(string $pluginPath, string $pluginName, string $viewType): void
    {
        if ($viewType === 'volt') {
            $content = "<?php

new class extends \\Livewire\\Volt\\Component
{
    public string \$message = 'Welcome to {$pluginName} Plugin!';

    public function mount(): void
    {
        \$this->message = 'Hello from {$pluginName}!';
    }
}
?>

<div class=\"p-6 bg-white rounded-lg shadow-md\">
    <h1 class=\"text-2xl font-bold text-gray-800 mb-4\">{$pluginName} Plugin</h1>
    <p class=\"text-gray-600 mb-4\">{{ \$message }}</p>

    <div class=\"bg-blue-50 border border-blue-200 rounded-lg p-4\">
        <h2 class=\"text-lg font-semibold text-blue-800 mb-2\">Plugin Information</h2>
        <ul class=\"text-blue-700 space-y-1\">
            <li><strong>Name:</strong> {$pluginName}</li>
            <li><strong>Status:</strong> Active</li>
            <li><strong>Created:</strong> {{ now()->format('Y-m-d H:i:s') }}</li>
            <li><strong>View Type:</strong> Livewire Volt</li>
        </ul>
    </div>
</div>
";
        } else {
            $content = "@extends('layouts.app')

@section('content')
<div class=\"p-6 bg-white rounded-lg shadow-md\">
    <h1 class=\"text-2xl font-bold text-gray-800 mb-4\">{$pluginName} Plugin</h1>
    <p class=\"text-gray-600 mb-4\">Welcome to {$pluginName} Plugin!</p>

    <div class=\"bg-blue-50 border border-blue-200 rounded-lg p-4\">
        <h2 class=\"text-lg font-semibold text-blue-800 mb-2\">Plugin Information</h2>
        <ul class=\"text-blue-700 space-y-1\">
            <li><strong>Name:</strong> {$pluginName}</li>
            <li><strong>Status:</strong> Active</li>
            <li><strong>Created:</strong> {{ now()->format('Y-m-d H:i:s') }}</li>
            <li><strong>View Type:</strong> Traditional Blade</li>
        </ul>
    </div>
</div>
@endsection
";
        }

        File::put($pluginPath.'/Views/index.blade.php', $content);
        $this->line("Created: index.blade.php ({$viewType})");
    }

    protected function createCommandFile(string $pluginPath, string $pluginName): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');
        $content = "<?php

namespace {$namespace}\\{$pluginName}\\Commands;

use Illuminate\\Console\\Command;

class {$pluginName}Command extends Command
{
    protected \$signature = '{$pluginName}:example {--option= : Example option}';

    protected \$description = 'Example command for {$pluginName} plugin';

    public function handle()
    {
        \$this->info('{$pluginName} command executed successfully!');
        
        if (\$option = \$this->option('option')) {
            \$this->line(\"Option value: {\$option}\");
        }

        return self::SUCCESS;
    }
}
";
        File::put($pluginPath.'/Commands/'.$pluginName.'Command.php', $content);
        $this->line("Created: {$pluginName}Command.php");
    }

    protected function createEventFile(string $pluginPath, string $pluginName): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');
        $content = "<?php

namespace {$namespace}\\{$pluginName}\\Events;

use Illuminate\\Broadcasting\\InteractsWithSockets;
use Illuminate\\Foundation\\Events\\Dispatchable;
use Illuminate\\Queue\\SerializesModels;

class {$pluginName}Event
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string \$message,
        public readonly array \$data = []
    ) {
    }
}
";
        File::put($pluginPath.'/Events/'.$pluginName.'Event.php', $content);
        $this->line("Created: {$pluginName}Event.php");
    }

    protected function createListenerFile(string $pluginPath, string $pluginName): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');
        $content = "<?php

namespace {$namespace}\\{$pluginName}\\Listeners;

use {$namespace}\\{$pluginName}\\Events\\{$pluginName}Event;
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Queue\\InteractsWithQueue;

class {$pluginName}Listener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle({$pluginName}Event \$event): void
    {
        logger('{$pluginName} event handled', [
            'message' => \$event->message,
            'data' => \$event->data,
        ]);
    }
}
";
        File::put($pluginPath.'/Listeners/'.$pluginName.'Listener.php', $content);
        $this->line("Created: {$pluginName}Listener.php");
    }

    protected function createEnumFile(string $pluginPath, string $pluginName): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');
        $content = "<?php

namespace {$namespace}\\{$pluginName}\\Enums;

enum {$pluginName}Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    case DISABLED = 'disabled';

    public function label(): string
    {
        return match(\$this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::PENDING => 'Pending',
            self::DISABLED => 'Disabled',
        };
    }

    public function color(): string
    {
        return match(\$this) {
            self::ACTIVE => 'green',
            self::INACTIVE => 'gray',
            self::PENDING => 'yellow',
            self::DISABLED => 'red',
        };
    }
}
";
        File::put($pluginPath.'/Enums/'.$pluginName.'Status.php', $content);
        $this->line("Created: {$pluginName}Status.php");
    }

    protected function createConcernFile(string $pluginPath, string $pluginName): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');
        $content = "<?php

namespace {$namespace}\\{$pluginName}\\Concerns;

trait Has{$pluginName}Features
{
    public function get{$pluginName}Info(): array
    {
        return [
            'plugin_name' => '{$pluginName}',
            'version' => '1.0.0',
            'status' => 'active',
            'created_at' => now()->toISOString(),
        ];
    }

    public function is{$pluginName}Active(): bool
    {
        return (bool)config('{$pluginName}.enabled', false);
    }

    public function get{$pluginName}Config(?string \$key = null, mixed \$default = null): mixed
    {
        \$config = config('{$pluginName}', []);
        
        if (\$key === null) {
            return \$config;
        }

        return data_get(\$config, \$key, \$default);
    }
}
";
        File::put($pluginPath.'/Concerns/Has'.$pluginName.'Features.php', $content);
        $this->line("Created: Has{$pluginName}Features.php");
    }
}
