<?php

namespace SoysalTan\LaravelPluginSystem\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use SoysalTan\LaravelPluginSystem\Services\PluginProfilingService;
use Carbon\Carbon;

class PluginDebugMiddleware
{
    protected PluginProfilingService $profilingService;
    protected array $debugData = [];
    protected float $startTime;
    protected int $startMemory;
    protected int $queryCount;

    public function __construct(PluginProfilingService $profilingService)
    {
        $this->profilingService = $profilingService;
    }

    public function handle(Request $request, Closure $next, ...$plugins)
    {
        if (!$this->shouldDebug($request)) {
            return $next($request);
        }

        $this->startDebugging($request, $plugins);

        $response = $next($request);

        $this->finishDebugging($request, $response, $plugins);

        return $response;
    }

    protected function shouldDebug(Request $request): bool
    {
        if (!config('app.debug', false)) {
            return false;
        }

        if ($request->hasHeader('X-Plugin-Debug')) {
            return true;
        }

        if ($request->query('plugin_debug') === '1') {
            return true;
        }

        $debugRoutes = config('laravel-plugin-system.debug.routes', []);
        if (!empty($debugRoutes)) {
            $currentRoute = $request->route()?->getName();
            return in_array($currentRoute, $debugRoutes);
        }

        return false;
    }

    protected function startDebugging(Request $request, array $plugins): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->queryCount = count(DB::getQueryLog());

        $this->debugData = [
            'request_id' => uniqid('req_'),
            'timestamp' => Carbon::now(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'plugins' => $plugins,
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'input' => $this->sanitizeInput($request->all()),
            'route' => [
                'name' => $request->route()?->getName(),
                'action' => $request->route()?->getActionName(),
                'parameters' => $request->route()?->parameters() ?? []
            ]
        ];

        foreach ($plugins as $plugin) {
            $this->profilingService->startProfiling($plugin);
        }

        $this->logDebugInfo('Request started', $this->debugData);
    }

    protected function finishDebugging(Request $request, $response, array $plugins): void
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endQueryCount = count(DB::getQueryLog());

        $executionTime = ($endTime - $this->startTime) * 1000;
        $memoryUsage = $endMemory - $this->startMemory;
        $queryCount = $endQueryCount - $this->queryCount;

        $responseData = [
            'status_code' => $response->getStatusCode(),
            'headers' => $this->sanitizeHeaders($response->headers->all()),
            'size' => strlen($response->getContent()),
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_bytes' => $memoryUsage,
            'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'query_count' => $queryCount,
            'queries' => $this->getExecutedQueries()
        ];

        $this->debugData['response'] = $responseData;
        $this->debugData['performance'] = [
            'execution_time_ms' => $responseData['execution_time_ms'],
            'memory_usage_mb' => $responseData['memory_usage_mb'],
            'query_count' => $queryCount,
            'is_slow' => $executionTime > 1000,
            'is_memory_heavy' => $memoryUsage > 50 * 1024 * 1024,
            'has_many_queries' => $queryCount > 10
        ];

        foreach ($plugins as $plugin) {
            $this->profilingService->stopProfiling($plugin);
            $profileData = $this->profilingService->getPluginProfiles($plugin, 1);
            $this->debugData['plugin_profiles'][$plugin] = $profileData;
        }

        $this->storeDebugData();
        $this->logDebugInfo('Request completed', $this->debugData);

        if ($request->wantsJson() || $request->hasHeader('X-Plugin-Debug-Response')) {
            $this->addDebugHeaders($response);
        }
    }

    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];
        
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                $headers[$key] = '[REDACTED]';
            }
        }

        return $headers;
    }

    protected function sanitizeInput(array $input): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key', 'secret'];
        
        foreach ($input as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $input[$key] = '[REDACTED]';
            }
        }

        return $input;
    }

    protected function getExecutedQueries(): array
    {
        $queries = DB::getQueryLog();
        $recentQueries = array_slice($queries, $this->queryCount);

        return array_map(function ($query) {
            return [
                'sql' => $query['query'],
                'bindings' => $query['bindings'],
                'time_ms' => $query['time'],
                'is_slow' => $query['time'] > 100
            ];
        }, $recentQueries);
    }

    protected function storeDebugData(): void
    {
        $cacheKey = 'plugin_debug_' . $this->debugData['request_id'];
        $ttl = config('laravel-plugin-system.debug.cache_ttl', 3600);
        
        Cache::put($cacheKey, $this->debugData, $ttl);

        $this->storeDebugHistory();
    }

    protected function storeDebugHistory(): void
    {
        $historyKey = 'plugin_debug_history';
        $history = Cache::get($historyKey, []);
        
        $history[] = [
            'request_id' => $this->debugData['request_id'],
            'timestamp' => $this->debugData['timestamp'],
            'url' => $this->debugData['url'],
            'method' => $this->debugData['method'],
            'status_code' => $this->debugData['response']['status_code'],
            'execution_time_ms' => $this->debugData['performance']['execution_time_ms'],
            'memory_usage_mb' => $this->debugData['performance']['memory_usage_mb'],
            'plugins' => $this->debugData['plugins']
        ];

        $maxHistory = config('laravel-plugin-system.debug.max_history', 100);
        if (count($history) > $maxHistory) {
            $history = array_slice($history, -$maxHistory);
        }

        Cache::put($historyKey, $history, 86400); // 24 hours
    }

    protected function addDebugHeaders($response): void
    {
        $response->headers->set('X-Plugin-Debug-Request-ID', $this->debugData['request_id']);
        $response->headers->set('X-Plugin-Debug-Execution-Time', $this->debugData['performance']['execution_time_ms'] . 'ms');
        $response->headers->set('X-Plugin-Debug-Memory-Usage', $this->debugData['performance']['memory_usage_mb'] . 'MB');
        $response->headers->set('X-Plugin-Debug-Query-Count', $this->debugData['performance']['query_count']);
        
        if (!empty($this->debugData['plugins'])) {
            $response->headers->set('X-Plugin-Debug-Plugins', implode(',', $this->debugData['plugins']));
        }
    }

    protected function logDebugInfo(string $message, array $data): void
    {
        $logChannel = config('laravel-plugin-system.debug.log_channel', 'single');
        
        Log::channel($logChannel)->info($message, [
            'request_id' => $data['request_id'],
            'url' => $data['url'],
            'method' => $data['method'],
            'plugins' => $data['plugins'] ?? [],
            'performance' => $data['performance'] ?? null
        ]);
    }

    public static function getDebugData(string $requestId): ?array
    {
        $cacheKey = 'plugin_debug_' . $requestId;
        return Cache::get($cacheKey);
    }

    public static function getDebugHistory(int $limit = 50): array
    {
        $history = Cache::get('plugin_debug_history', []);
        return array_slice($history, -$limit);
    }

    public static function clearDebugHistory(): void
    {
        Cache::forget('plugin_debug_history');
        
        try {
            $pluginsPath = config('laravel-plugin-system.plugins_path', app_path('Plugins'));
            $pluginDirectories = \Illuminate\Support\Facades\File::directories($pluginsPath);
            
            foreach ($pluginDirectories as $pluginDir) {
                $pluginName = basename($pluginDir);
                $cacheKey = 'plugin_debug_' . $pluginName;
                Cache::forget($cacheKey);
            }
            
            for ($i = 0; $i < 1000; $i++) {
                $cacheKey = 'plugin_debug_req_' . str_pad($i, 6, '0', STR_PAD_LEFT);
                if (!Cache::has($cacheKey)) {
                    break;
                }
                Cache::forget($cacheKey);
            }
        } catch (\Exception $e) {
            Log::warning("Error clearing debug history: " . $e->getMessage());
        }
    }
}