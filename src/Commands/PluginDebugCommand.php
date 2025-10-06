<?php

namespace SoysalTan\LaravelPluginSystem\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use SoysalTan\LaravelPluginSystem\Services\PluginProfilingService;

class PluginDebugCommand extends Command
{
    protected $signature = 'plugin:debug {plugin?} 
                           {--trace : Enable execution trace}
                           {--memory : Monitor memory usage}
                           {--queries : Track database queries}
                           {--slow-queries=1000 : Highlight queries slower than X ms}
                           {--watch : Watch mode for continuous monitoring}
                           {--output=table : Output format (table, json, detailed)}
                           {--filter= : Filter debug output by type}';

    protected $description = 'Debug plugin execution with detailed monitoring';

    protected PluginProfilingService $profilingService;

    public function __construct(PluginProfilingService $profilingService)
    {
        parent::__construct();
        $this->profilingService = $profilingService;
    }

    public function handle(): int
    {
        $pluginName = $this->argument('plugin');
        $watchMode = $this->option('watch');

        if ($watchMode) {
            return $this->handleWatchMode($pluginName);
        }

        if ($pluginName) {
            return $this->debugSpecificPlugin($pluginName);
        }

        return $this->debugAllPlugins();
    }

    protected function debugSpecificPlugin(string $pluginName): int
    {
        $this->info("🔍 Debugging plugin: {$pluginName}");
        $this->newLine();

        if (!$this->pluginExists($pluginName)) {
            $this->error("Plugin '{$pluginName}' not found!");
            return 1;
        }

        $debugData = $this->collectDebugData($pluginName);
        $this->displayDebugResults($debugData);

        return 0;
    }

    protected function debugAllPlugins(): int
    {
        $this->info('🔍 Debugging all plugins');
        $this->newLine();

        $pluginsPath = config('laravel-plugin-system.plugins_path', app_path('Plugins'));

        if (!File::exists($pluginsPath)) {
            $this->error('Plugins directory not found!');
            return 1;
        }

        $plugins = collect(File::directories($pluginsPath))
            ->map(fn($dir) => basename($dir))
            ->filter(fn($plugin) => $this->isPluginEnabled($plugin));

        if ($plugins->isEmpty()) {
            $this->warn('No enabled plugins found.');
            return 0;
        }

        foreach ($plugins as $plugin) {
            $this->line("Debugging: {$plugin}");

            $debugData = $this->collectDebugData($plugin);

            $this->displaySummaryResults($plugin, $debugData);

            $this->newLine();
        }

        return 0;
    }

    protected function handleWatchMode(string $pluginName = null): int
    {
        $this->info('👀 Starting watch mode (Press Ctrl+C to exit)');
        $this->newLine();

        try {
            while (true) {
                $this->line('[' . now()->format('H:i:s') . '] Collecting debug data...');

                if ($pluginName) {
                    $debugData = $this->collectDebugData($pluginName);
                    $this->displayWatchResults($pluginName, $debugData);
                } else {
                    $this->debugAllPlugins();
                }

                sleep(5);
                $this->newLine();
            }
        } catch (\Exception $e) {
            $this->error('Watch mode interrupted: ' . $e->getMessage());
        }

        return 0;
    }

    protected function collectDebugData(string $pluginName): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $queryCount = 0;
        $slowQueries = [];

        if ($this->option('queries')) {
            DB::enableQueryLog();
        }

        try {
            $pluginData = $this->getPluginInformation($pluginName);

            if ($this->option('trace')) {
                $traceData = $this->collectTraceData($pluginName);
                $pluginData['trace'] = $traceData;
            }

            if ($this->option('queries')) {
                $queries = DB::getQueryLog();
                $queryCount = count($queries);
                $slowQueryThreshold = (int) $this->option('slow-queries');

                $slowQueries = collect($queries)
                    ->filter(fn($query) => $query['time'] > $slowQueryThreshold)
                    ->values()
                    ->toArray();
            }

        } catch (\Exception $e) {
            $pluginData = [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        return [
            'plugin_name' => $pluginName,
            'execution_time' => round(($endTime - $startTime) * 1000, 2),
            'memory_usage' => [
                'start' => $this->formatBytes($startMemory),
                'end' => $this->formatBytes($endMemory),
                'peak' => $this->formatBytes(memory_get_peak_usage(true)),
                'difference' => $this->formatBytes($endMemory - $startMemory)
            ],
            'query_count' => $queryCount,
            'slow_queries' => $slowQueries,
            'plugin_data' => $pluginData,
            'timestamp' => now()->toISOString()
        ];
    }

    protected function getPluginInformation(string $pluginName): array
    {
        $pluginsPath = config('laravel-plugin-system.plugins_path', app_path('Plugins'));
        $pluginPath = $pluginsPath . '/' . $pluginName;

        $info = [
            'name' => $pluginName,
            'path' => $pluginPath,
            'enabled' => $this->isPluginEnabled($pluginName),
            'files' => [],
            'routes' => [],
            'services' => [],
            'config' => []
        ];

        if (File::exists($pluginPath . '/config.php')) {
            $info['config'] = include $pluginPath . '/config.php';
        }

        if (File::exists($pluginPath . '/routes.php')) {
            $info['routes'] = $this->analyzeRoutes($pluginPath . '/routes.php');
        }

        $info['files'] = $this->getPluginFiles($pluginPath);
        $info['services'] = $this->getPluginServices($pluginName);

        return $info;
    }

    protected function collectTraceData(string $pluginName): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10);

        return collect($trace)
            ->map(function ($item) {
                return [
                    'file' => $item['file'] ?? 'unknown',
                    'line' => $item['line'] ?? 0,
                    'function' => $item['function'] ?? 'unknown',
                    'class' => $item['class'] ?? null,
                ];
            })
            ->toArray();
    }

    protected function analyzeRoutes(string $routesFile): array
    {
        $content = File::get($routesFile);
        $routes = [];

        if (preg_match_all('/Route::(\w+)\([\'"]([^\'"]+)[\'"]/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $routes[] = [
                    'method' => strtoupper($match[1]),
                    'uri' => $match[2]
                ];
            }
        }

        if (preg_match_all('/Volt::route\([\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $uri) {
                $routes[] = [
                    'method' => 'GET',
                    'uri' => $uri,
                    'type' => 'volt'
                ];
            }
        }

        return $routes;
    }

    protected function getPluginFiles(string $pluginPath): array
    {
        if (!File::exists($pluginPath)) {
            return [];
        }

        return collect(File::allFiles($pluginPath))
            ->map(function ($file) {
                return [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $this->formatBytes($file->getSize()),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                ];
            })
            ->toArray();
    }

    protected function getPluginServices(string $pluginName): array
    {
        $services = [];
        $namespace = config('laravel-plugin-system.plugin_namespace', 'App\\Plugins') . '\\' . $pluginName . '\\Services';

        try {
            $serviceInterface = $namespace . '\\' . $pluginName . 'ServiceInterface';
            $serviceClass = $namespace . '\\' . $pluginName . 'Service';

            if (app()->bound($serviceInterface)) {
                $services[] = [
                    'interface' => $serviceInterface,
                    'implementation' => $serviceClass,
                    'bound' => true
                ];
            }
        } catch (\Exception $e) {
            // Service not found or not bound
        }

        return $services;
    }

    protected function displayDebugResults(array $debugData): void
    {
        $outputFormat = $this->option('output');

        switch ($outputFormat) {
            case 'json':
                $this->line(json_encode($debugData, JSON_PRETTY_PRINT));
                break;
            case 'detailed':
                $this->displayDetailedResults($debugData);
                break;
            default:
                $this->displayTableResults($debugData);
        }
    }

    protected function displayTableResults(array $debugData): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Plugin Name', $debugData['plugin_name']],
                ['Execution Time', $debugData['execution_time'] . ' ms'],
                ['Memory Usage (Peak)', $debugData['memory_usage']['peak']],
                ['Memory Difference', $debugData['memory_usage']['difference']],
                ['Query Count', $debugData['query_count']],
                ['Slow Queries', count($debugData['slow_queries'])],
                ['Status', isset($debugData['plugin_data']['error']) ? '❌ Error' : '✅ OK']
            ]
        );

        if (isset($debugData['plugin_data']['error'])) {
            $this->error('Error: ' . $debugData['plugin_data']['error']);
            $this->line('File: ' . $debugData['plugin_data']['file']);
            $this->line('Line: ' . $debugData['plugin_data']['line']);
        }

        if (!empty($debugData['slow_queries'])) {
            $this->warn('Slow Queries Detected:');
            foreach ($debugData['slow_queries'] as $query) {
                $this->line("• {$query['query']} ({$query['time']} ms)");
            }
        }
    }

    protected function displayDetailedResults(array $debugData): void
    {
        $this->info("=== Plugin Debug Report: {$debugData['plugin_name']} ===");
        $this->newLine();

        $this->line("📊 Performance Metrics:");
        $this->line("  Execution Time: {$debugData['execution_time']} ms");
        $this->line("  Memory Start: {$debugData['memory_usage']['start']}");
        $this->line("  Memory End: {$debugData['memory_usage']['end']}");
        $this->line("  Memory Peak: {$debugData['memory_usage']['peak']}");
        $this->line("  Memory Diff: {$debugData['memory_usage']['difference']}");
        $this->newLine();

        $this->line("🗃️ Database Metrics:");
        $this->line("  Total Queries: {$debugData['query_count']}");
        $this->line("  Slow Queries: " . count($debugData['slow_queries']));
        $this->newLine();

        if (isset($debugData['plugin_data']['config'])) {
            $this->line("⚙️ Plugin Configuration:");
            foreach ($debugData['plugin_data']['config'] as $key => $value) {
                $this->line("  {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value));
            }
            $this->newLine();
        }

        if (!empty($debugData['plugin_data']['routes'])) {
            $this->line("🛣️ Plugin Routes:");
            foreach ($debugData['plugin_data']['routes'] as $route) {
                $type = isset($route['type']) ? " ({$route['type']})" : '';
                $this->line("  {$route['method']} {$route['uri']}{$type}");
            }
            $this->newLine();
        }

        if (!empty($debugData['plugin_data']['services'])) {
            $this->line("🔧 Plugin Services:");
            foreach ($debugData['plugin_data']['services'] as $service) {
                $status = $service['bound'] ? '✅' : '❌';
                $this->line("  {$status} {$service['interface']}");
            }
            $this->newLine();
        }
    }

    protected function displaySummaryResults(string $pluginName, array $debugData): void
    {
        $status = isset($debugData['plugin_data']['error']) ? '❌' : '✅';
        $memory = $debugData['memory_usage']['peak'];
        $time = $debugData['execution_time'];
        $queries = $debugData['query_count'];

        $this->line("  {$status} {$pluginName} | {$time}ms | {$memory} | {$queries} queries");
    }

    protected function displayWatchResults(string $pluginName, array $debugData): void
    {
        $status = isset($debugData['plugin_data']['error']) ? '❌ ERROR' : '✅ OK';
        $this->line("[{$pluginName}] {$status} | {$debugData['execution_time']}ms | {$debugData['memory_usage']['peak']} | {$debugData['query_count']} queries");

        if (isset($debugData['plugin_data']['error'])) {
            $this->error("  Error: {$debugData['plugin_data']['error']}");
        }
    }

    protected function pluginExists(string $pluginName): bool
    {
        $pluginsPath = config('laravel-plugin-system.plugins_path', app_path('Plugins'));
        return File::exists($pluginsPath . '/' . $pluginName);
    }

    protected function isPluginEnabled(string $pluginName): bool
    {
        return (bool) config("{$pluginName}.enabled", false);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
