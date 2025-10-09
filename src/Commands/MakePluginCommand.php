<?php

namespace SoysalTan\LaravelPluginSystem\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SoysalTan\LaravelPluginSystem\PluginManager;
use Symfony\Component\Console\Command\Command as CommandAlias;

class MakePluginCommand extends Command
{
    protected $signature = 'make:plugin {pluginName : The name of the plugin}
                            {--view-type=auto : View type (volt, blade, auto)}
                            {--command= : Create a command class}
                            {--controller= : Create a controller file}
                            {--listener= : Create a listener class}
                            {--event= : Create an event class}
                            {--view= : Create a view file}
                            {--route : Create a route file}
                            {--enum= : Create an enum file}
                            {--trait= : Create a trait file}';

    protected $description = 'Create a new plugin with all necessary directories and files';

    public function handle()
    {
        $pluginName = $this->argument('pluginName');
        $pluginName = Str::studly($pluginName);

        $pluginsPath = config('laravel-plugin-system.plugins_path', app_path('Plugins'));
        $pluginPath = $pluginsPath . "/{$pluginName}";

        if (File::exists($pluginPath)) {
            // Check if any component options are provided
            $hasComponentOptions = $this->option('command') || $this->option('controller') ||
                $this->option('event') || $this->option('listener') ||
                $this->option('view') || $this->option('enum') || $this->option('trait');

            if (!$hasComponentOptions) {
                $this->error("Plugin '{$pluginName}' already exists!");
                return CommandAlias::FAILURE;
            }

            // Determine view type for existing plugin
            $viewType = $this->determineViewType();

            // Only create additional components
            $this->createAdditionalComponents($pluginPath, $pluginName, $viewType);

            return CommandAlias::SUCCESS;
        }

        // Determine view type
        $viewType = $this->determineViewType();

        $this->info("Creating plugin: {$pluginName}");
        $this->info("View type: {$viewType}");

        $this->createPluginDirectories($pluginPath);
        $this->createPluginFiles($pluginPath, $pluginName, $viewType);

        // Create additional components based on options
        $this->createAdditionalComponents($pluginPath, $pluginName, $viewType);

        $this->createProviderFile($pluginPath, $pluginName);

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
            $pluginPath . '/Controllers',
            $pluginPath . '/Services',
            $pluginPath . '/Views',
            $pluginPath . '/Commands',
            $pluginPath . '/Events',
            $pluginPath . '/Listeners',
            $pluginPath . '/Enums',
            $pluginPath . '/Concerns',
        ];

        foreach ($directories as $directory) {
            !File::exists($directory) && File::makeDirectory($directory, 0755, true);

            $this->line('Created directory: ' . basename($directory));
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
        File::put($pluginPath . '/config.php', $content);
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

        File::put($pluginPath . '/routes.php', $content);
        $this->line('Created: routes.php');
    }

    protected function createControllerFile(string $pluginPath, string $pluginName, ?string $controllerName = null): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');

        if ($controllerName) {
            if (str_ends_with($controllerName, 'Controller')) {
                $controllerClass = Str::studly($controllerName);
            } else {
                $controllerClass = Str::studly("{$controllerName}Controller");
            }

            $fileName = $controllerClass . '.php';
        } else {
            $controllerClass = $pluginName . 'Controller';
            $fileName = $controllerClass . '.php';
        }

        $filePath = $pluginPath . '/Controllers/' . $fileName;
        if (File::exists($filePath)) {
            $this->warn("Controller file '{$fileName}' already exists in plugin '{$pluginName}'. Skipping...");
            return;
        }

        if ($controllerName) {
            $content = "<?php

namespace {$namespace}\\{$pluginName}\\Controllers;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\View\\View;

class {$controllerClass} extends Controller
{
    public function index(): View
    {
        return view('{$pluginName}::index', [
            'title' => '{$pluginName} index Page',
            'message' => 'Welcome to {$pluginName} plugin!'
        ]);
    }

    public function store(Request \$request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data stored successfully',
            'data' => \$request->all()
        ]);
    }

    public function show(\$id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => \$id,
                'plugin' => '{$pluginName}',
                'created_at' => now()
            ]
        ]);
    }

    public function update(Request \$request, \$id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data updated successfully',
            'id' => \$id,
            'data' => \$request->all()
        ]);
    }

    public function destroy(\$id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data deleted successfully',
            'id' => \$id
        ]);
    }
}
";
        } else {
            $controllerClass = $pluginName . 'Controller';
            $fileName = $pluginName . 'Controller.php';

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
        }

        File::put($pluginPath . '/Controllers/' . $fileName, $content);
        $this->line("Created: {$fileName}");
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

        File::put($pluginPath . '/Services/' . $pluginName . 'ServiceInterface.php', $interfaceContent);
        File::put($pluginPath . '/Services/' . $pluginName . 'Service.php', $serviceContent);

        $this->line("Created: {$pluginName}ServiceInterface.php");
        $this->line("Created: {$pluginName}Service.php");
    }

    protected function createViewFile(string $pluginPath, string $pluginName, string $viewType, ?string $viewName = null): void
    {
        $fileName = $viewName ? $viewName . '.blade.php' : 'index.blade.php';

        $filePath = $pluginPath . '/Views/' . $fileName;
        if (File::exists($filePath)) {
            $this->warn("View file '{$fileName}' already exists in plugin '{$pluginName}'. Skipping...");
            return;
        }

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

        File::put($filePath, $content);
        $this->line("Created: {$fileName} ({$viewType})");
    }

    protected function createCommandFile(string $pluginPath, string $pluginName, ?string $commandName = null): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');

        if ($commandName) {
            $commandClass = Str::studly($commandName);
            $commandSignature = strtolower($pluginName) . ':' . Str::kebab($commandName) . ' {--option= : Example option}';
            $fileName = $commandClass . '.php';
        } else {
            $commandClass = $pluginName . 'Command';
            $commandSignature = $pluginName . ':example {--option= : Example option}';
            $fileName = $pluginName . 'Command.php';
        }

        $filePath = $pluginPath . '/Commands/' . $fileName;

        if (File::exists($filePath)) {
            $this->warn("Command file '{$fileName}' already exists in plugin '{$pluginName}'. Skipping...");
            return;
        }

        $content = "<?php

namespace {$namespace}\\{$pluginName}\\Commands;

use Illuminate\\Console\\Command;

class {$commandClass} extends Command
{
    protected \$signature = '{$commandSignature}';

    protected \$description = 'Command for {$pluginName} plugin';

    public function handle()
    {
        \$this->info('Executing {$commandClass} command for {$pluginName} plugin');

        if (\$option = \$this->option('option')) {
            \$this->line(\"Option value: {\$option}\");
        }

        return self::SUCCESS;
    }
}
";
        File::put($pluginPath . '/Commands/' . $fileName, $content);
        $this->line("Created: {$fileName}");
    }

    protected function createEventFile(string $pluginPath, string $pluginName, ?string $eventName = null): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');

        if ($eventName) {
            $eventClass = Str::studly($eventName);
            $fileName = $eventClass . '.php';
        } else {
            $eventClass = $pluginName . 'Event';
            $fileName = $eventClass . '.php';
        }

        $filePath = $pluginPath . '/Events/' . $fileName;
        if (File::exists($filePath)) {
            $this->warn("Event file '{$fileName}' already exists in plugin '{$pluginName}'. Skipping...");
            return;
        }

        if ($eventName) {
            $content = "<?php

namespace {$namespace}\\{$pluginName}\\Events;

use Illuminate\\Broadcasting\\Channel;
use Illuminate\\Broadcasting\\InteractsWithSockets;
use Illuminate\\Broadcasting\\PresenceChannel;
use Illuminate\\Broadcasting\\PrivateChannel;
use Illuminate\\Contracts\\Broadcasting\\ShouldBroadcast;
use Illuminate\\Foundation\\Events\\Dispatchable;
use Illuminate\\Queue\\SerializesModels;

class {$eventClass}
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array \$data = []
    ) {}

    public function getData(): array
    {
        return \$this->data;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('{$pluginName}-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return '{$pluginName}.{$eventName}';
    }
}
";
        } else {
            $eventClass = $pluginName . 'Event';
            $fileName = $eventClass . '.php';

            $content = "<?php

namespace {$namespace}\\{$pluginName}\\Events;

use Illuminate\\Broadcasting\\InteractsWithSockets;
use Illuminate\\Foundation\\Events\\Dispatchable;
use Illuminate\\Queue\\SerializesModels;

class {$eventClass}
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string \$message,
        public readonly array \$data = []
    ) {
    }
}
";
        }

        File::put($pluginPath . '/Events/' . $fileName, $content);
        $this->line("Created: {$fileName}");
    }

    protected function createListenerFile(string $pluginPath, string $pluginName, ?string $listenerName = null): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');

        if ($listenerName) {
            $listenerClass = Str::studly($listenerName);
            $eventClass = Str::studly(str_replace('Listener', 'Event', $listenerName));
            $fileName = $listenerClass . '.php';
        } else {
            $listenerClass = $pluginName . 'Listener';
            $fileName = $listenerClass . '.php';
        }

        $filePath = $pluginPath . '/Listeners/' . $fileName;

        if (File::exists($filePath)) {
            $this->warn("Listener file '{$fileName}' already exists in plugin '{$pluginName}'. Skipping...");
            return;
        }

        if ($listenerName) {
            $content = "<?php

namespace {$namespace}\\{$pluginName}\\Listeners;

use {$namespace}\\{$pluginName}\\Events\\{$eventClass};
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Queue\\InteractsWithQueue;

class {$listenerClass} implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle({$eventClass} \$event): void
    {
        \$data = \$event->getData();

        logger()->info('{$pluginName} {$listenerClass} handled', [
            'event' => get_class(\$event),
            'data' => \$data,
            'timestamp' => now()
        ]);
    }

    public function failed({$eventClass} \$event, \$exception): void
    {
        logger()->error('{$pluginName} {$listenerClass} failed', [
            'event' => get_class(\$event),
            'exception' => \$exception->getMessage(),
            'timestamp' => now()
        ]);
    }
}
";
        } else {
            $listenerClass = $pluginName . 'Listener';
            $fileName = $pluginName . 'Listener.php';

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
        }

        File::put($pluginPath . '/Listeners/' . $fileName, $content);
        $this->line("Created: {$fileName}");
    }

    protected function createEnumFile(string $pluginPath, string $pluginName, string $enum): void
    {
        $filePath = "{$pluginPath}/Enums/{$enum}.php";
        $enumName = ucfirst($enum);

        if (File::exists($filePath)) {
            $this->warn("Enum file '{$enumName}' already exists in plugin '{$pluginName}'. Skipping...");
            return;
        }

        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');

        $content = "<?php

namespace {$namespace}\\{$pluginName}\\Enums;

enum $enumName : string
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
        File::put($filePath, $content);
        $this->line("Created enum: {$filePath}");
    }

    protected function createConcernFile(string $pluginPath, string $pluginName,string $trait): void
    {
        $filePath = "{$pluginPath}/Concerns/{$trait}.php";
        $traitName = ucfirst($trait);

        if (File::exists($filePath)) {
            $this->warn("Trait file '{$traitName}' already exists in plugin '{$pluginName}'. Skipping...");
            return;
        }

        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');

        $content = "<?php

namespace {$namespace}\\{$pluginName}\\Concerns;

trait $traitName
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
        File::put($filePath, $content);
        $this->line("Created trait: {$filePath}");
    }

    protected function createAdditionalComponents(string $pluginPath, string $pluginName, string $viewType): void
    {
        $this->createPluginDirectories($pluginPath);

        if ($this->option('command')) {
            $this->createCommandFile($pluginPath, $pluginName, $this->option('command'));
        }

        if ($this->option('controller')) {
            $this->createControllerFile($pluginPath, $pluginName, $this->option('controller'));
        }

        if ($this->option('listener')) {
            $this->createListenerFile($pluginPath, $pluginName, $this->option('listener'));
        }

        if ($this->option('event')) {
            $this->createEventFile($pluginPath, $pluginName, $this->option('event'));
        }

        if ($this->option('view')) {
            $this->createViewFile($pluginPath, $pluginName, $viewType, $this->option('view'));
        }

        if ($this->option('route')) {
            $controllerName = $this->option('controller') ?? $pluginName;
            $this->createRouteFile($pluginPath, $pluginName, $controllerName);
        }

        if ($this->option('enum')) {
            $this->createEnumFile($pluginPath, $pluginName, $this->option('enum'));
        }

        if ($this->option('trait')) {
            $this->createConcernFile($pluginPath, $pluginName, $this->option('trait'));
        }
    }

    protected function createRouteFile(string $pluginPath, string $pluginName, ?string $controllerName): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');

        $fileName = 'routes.php';
        $filePath = $pluginPath . '/' . $fileName;

        $pluginNameLower = strtolower($pluginName);
        $controllerClass = $controllerName . 'Controller';

        $newRoutes = "
Route::controller({$controllerClass}::class)->group(function () {
    Route::get('/', 'index')->name('{$pluginNameLower}.index');
    Route::post('/',  'store')->name('{$pluginNameLower}.store');
    Route::get('/{{id}}', 'show')->name('{$pluginNameLower}.show');
    Route::put('/{{id}}', 'update')->name('{$pluginNameLower}.update');
    Route::delete('/{{id}}', 'destroy')->name('{$pluginNameLower}.destroy');
});";

        if (File::exists($filePath)) {
            $existingContent = File::get($filePath);

            // Controller use statement'ını kontrol et ve ekle
            $useStatement = "use {$namespace}\\{$pluginName}\\Controllers\\{$controllerClass};";
            if (!str_contains($existingContent, $useStatement)) {
                // Son use statement'tan sonra ekle
                if (preg_match('/^use .+;$/m', $existingContent)) {
                    $existingContent = preg_replace('/^(use .+;)$/m', "$1\n{$useStatement}", $existingContent, 1);
                } else {
                    // Hiç use statement yoksa <?php'den sonra ekle
                    $existingContent = preg_replace('/^<\?php\s*$/m', "<?php\n\n{$useStatement}", $existingContent);
                }
            }

            // Route'ları dosyanın sonuna ekle
            $existingContent = rtrim($existingContent) . $newRoutes . "\n";

            File::put($filePath, $existingContent);
            $this->line("Added routes to existing: {$fileName}");
        } else {
            $content = "<?php

use Illuminate\\Support\\Facades\\Route;
use {$namespace}\\{$pluginName}\\Controllers\\{$controllerClass};

{$newRoutes}
";
            File::put($filePath, $content);
            $this->line("Created: {$fileName}");
        }
    }

    protected function createProviderFile(string $pluginPath, string $pluginName): void
    {
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins');

        $fileName = $pluginName . 'Provider.php';
        $filePath = $pluginPath . '/' . $fileName;

        if (File::exists($filePath)) {
            $this->warn("Provider file '{$fileName}' already exists in plugin '{$pluginName}'. Skipping...");
            return;
        }

        $content = "<?php

namespace {$namespace}\\{$pluginName};

use Illuminate\\Support\\ServiceProvider;

class {$pluginName}Provider extends ServiceProvider
{
    public function register(): void
    {
        // Register plugin services here
    }

    public function boot(): void
    {
        // Boot plugin services here
    }
}
";

        File::put($filePath, $content);
        $this->line("Created: {$fileName}");
    }
}
