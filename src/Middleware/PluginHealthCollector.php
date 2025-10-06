<?php

namespace SoysalTan\LaravelPluginSystem\Middleware;

use Closure;
use Illuminate\Http\Request;
use SoysalTan\LaravelPluginSystem\Services\PluginHealthMonitor;
use Symfony\Component\HttpFoundation\Response;

class PluginHealthCollector
{
    protected PluginHealthMonitor $healthMonitor;

    /**
     * @throws \Throwable
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('laravel-plugin-system.health_monitoring.enabled', false)) {
            return $next($request);
        }

        $this->healthMonitor = app(PluginHealthMonitor::class);

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Detect which plugin is handling this request
        $pluginName = $this->detectPluginFromRequest($request);

        if (!$pluginName) {
            return $next($request);
        }

        try {
            $response = $next($request);

            // Record successful request metrics
            $this->recordMetrics($pluginName, $startTime, $startMemory, true);

            return $response;

        } catch (\Throwable $exception) {
            // Record error metrics
            $this->recordMetrics($pluginName, $startTime, $startMemory, false);
            $this->healthMonitor->recordPluginError($pluginName, $exception);

            throw $exception;
        }
    }

    protected function detectPluginFromRequest(Request $request): ?string
    {
        $path = $request->path();

        // Check if request matches plugin route pattern
        if (preg_match('/^(?:plugins\/)?([^\/]+)/', $path, $matches)) {
            $potentialPlugin = ucfirst($matches[1]);

            // Verify plugin exists
            $pluginsPath = config('laravel-plugin-system.plugins_path', app_path('Plugins'));
            if (is_dir($pluginsPath.'/'.$potentialPlugin)) {
                return $potentialPlugin;
            }
        }

        return null;
    }

    protected function recordMetrics(string $pluginName, float $startTime, int $startMemory, bool $success): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = memory_get_usage(true) - $startMemory;

        // Record execution time
        $this->healthMonitor->recordPluginMetric($pluginName, 'execution_time', $executionTime);

        // Record memory usage
        $this->healthMonitor->recordPluginMetric($pluginName, 'memory_usage', $memoryUsage);

        // Increment request count
        $currentMetrics = $this->healthMonitor->getPluginMetrics($pluginName);
        $this->healthMonitor->recordPluginMetric($pluginName, 'request_count', ($currentMetrics['request_count'] ?? 0) + 1);

        // Record response time
        $this->healthMonitor->recordPluginMetric($pluginName, 'response_time', $executionTime);

        if (!$success) {
            // Increment error count
            $this->healthMonitor->recordPluginMetric($pluginName, 'error_count', ($currentMetrics['error_count'] ?? 0) + 1);
        }
    }
}
