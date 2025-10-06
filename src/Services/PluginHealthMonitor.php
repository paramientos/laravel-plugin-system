<?php

namespace SoysalTan\LaravelPluginSystem\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use SoysalTan\LaravelPluginSystem\Contracts\PluginHealthMonitorInterface;
use Carbon\Carbon;

class PluginHealthMonitor implements PluginHealthMonitorInterface
{
    protected array $healthThresholds = [
        'memory_usage' => 50 * 1024 * 1024, // 50MB
        'execution_time' => 5000, // 5 seconds in milliseconds
        'error_rate' => 0.05, // 5% error rate
        'response_time' => 2000, // 2 seconds
        'cpu_usage' => 80, // 80% CPU usage
    ];

    protected string $cachePrefix = 'plugin_health_';
    protected int $cacheTtl = 300; // 5 minutes

    public function checkPluginHealth(string $pluginName): array
    {
        $cacheKey = $this->cachePrefix . 'check_' . $pluginName;

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($pluginName) {
            $metrics = $this->getPluginMetrics($pluginName);
            $errors = $this->getPluginErrors($pluginName, 5);
            $uptime = $this->getPluginUptime($pluginName);

            $health = [
                'plugin_name' => $pluginName,
                'status' => $this->determineHealthStatus($metrics, $errors),
                'uptime' => $uptime,
                'last_check' => now()->toISOString(),
                'metrics' => $metrics,
                'recent_errors' => $errors,
                'recommendations' => $this->generateRecommendations($metrics, $errors),
            ];

            return $health;
        });
    }

    public function checkAllPluginsHealth(): array
    {
        $pluginsPath = config('laravel-plugin-system.plugins_path', app_path('Plugins'));

        if (!File::exists($pluginsPath)) {
            return [];
        }

        $pluginDirectories = File::directories($pluginsPath);
        $healthReports = [];

        foreach ($pluginDirectories as $pluginDir) {
            $pluginName = basename($pluginDir);

            if ($this->isPluginEnabled($pluginName)) {
                $healthReports[$pluginName] = $this->checkPluginHealth($pluginName);
            }
        }

        return $healthReports;
    }

    public function getPluginMetrics(string $pluginName): array
    {
        $cacheKey = $this->cachePrefix . 'metrics_' . $pluginName;

        return Cache::get($cacheKey, [
            'memory_usage' => 0,
            'execution_time' => 0,
            'request_count' => 0,
            'error_count' => 0,
            'last_activity' => null,
            'cpu_usage' => 0,
            'response_time' => 0,
            'database_queries' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
        ]);
    }

    public function isPluginHealthy(string $pluginName): bool
    {
        $health = $this->checkPluginHealth($pluginName);
        return $health['status'] === 'healthy';
    }

    public function getHealthThresholds(): array
    {
        return $this->healthThresholds;
    }

    public function setHealthThreshold(string $metric, $value): void
    {
        $this->healthThresholds[$metric] = $value;

        // Save to config or cache for persistence
        Cache::forever('plugin_health_thresholds', $this->healthThresholds);
    }

    public function getPluginErrors(string $pluginName, int $limit = 10): array
    {
        $cacheKey = $this->cachePrefix . 'errors_' . $pluginName;
        $errors = Cache::get($cacheKey, []);

        return array_slice($errors, 0, $limit);
    }

    public function clearPluginErrors(string $pluginName): void
    {
        $cacheKey = $this->cachePrefix . 'errors_' . $pluginName;
        Cache::forget($cacheKey);
    }

    public function getPluginUptime(string $pluginName): float
    {
        $cacheKey = $this->cachePrefix . 'uptime_' . $pluginName;

        $uptimeData = Cache::get($cacheKey, [
            'start_time' => now(),
            'downtime_duration' => 0,
        ]);

        $totalTime = now()->diffInSeconds($uptimeData['start_time']);
        $uptime = ($totalTime - $uptimeData['downtime_duration']) / $totalTime;

        return max(0, min(1, $uptime)) * 100; // Return as percentage
    }

    public function recordPluginError(string $pluginName, \Throwable $exception): void
    {
        $cacheKey = $this->cachePrefix . 'errors_' . $pluginName;
        $errors = Cache::get($cacheKey, []);

        $errorData = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toISOString(),
            'severity' => $this->determineSeverity($exception),
        ];

        array_unshift($errors, $errorData);

        // Keep only last 50 errors
        $errors = array_slice($errors, 0, 50);

        Cache::put($cacheKey, $errors, now()->addDays(7));

        // Update error count metric
        $this->incrementMetric($pluginName, 'error_count');

        // Log critical errors
        if ($errorData['severity'] === 'critical') {
            Log::critical("Plugin {$pluginName} critical error: " . $exception->getMessage(), [
                'plugin' => $pluginName,
                'exception' => $exception,
            ]);
        }
    }

    public function recordPluginMetric(string $pluginName, string $metric, $value): void
    {
        $cacheKey = $this->cachePrefix . 'metrics_' . $pluginName;
        $metrics = Cache::get($cacheKey, []);

        $metrics[$metric] = $value;
        $metrics['last_activity'] = now()->toISOString();

        Cache::put($cacheKey, $metrics, now()->addHours(24));
    }

    public function getHealthReport(): array
    {
        $allHealth = $this->checkAllPluginsHealth();

        $summary = [
            'total_plugins' => count($allHealth),
            'healthy_plugins' => 0,
            'warning_plugins' => 0,
            'critical_plugins' => 0,
            'overall_status' => 'healthy',
            'last_check' => now()->toISOString(),
        ];

        foreach ($allHealth as $health) {
            switch ($health['status']) {
                case 'healthy':
                    $summary['healthy_plugins']++;
                    break;
                case 'warning':
                    $summary['warning_plugins']++;
                    break;
                case 'critical':
                    $summary['critical_plugins']++;
                    break;
            }
        }

        if ($summary['critical_plugins'] > 0) {
            $summary['overall_status'] = 'critical';
        } elseif ($summary['warning_plugins'] > 0) {
            $summary['overall_status'] = 'warning';
        }

        return [
            'summary' => $summary,
            'plugins' => $allHealth,
        ];
    }

    protected function determineHealthStatus(array $metrics, array $errors): string
    {
        $criticalIssues = 0;
        $warningIssues = 0;

        // Check memory usage
        if ($metrics['memory_usage'] > $this->healthThresholds['memory_usage']) {
            $criticalIssues++;
        } elseif ($metrics['memory_usage'] > $this->healthThresholds['memory_usage'] * 0.8) {
            $warningIssues++;
        }

        // Check execution time
        if ($metrics['execution_time'] > $this->healthThresholds['execution_time']) {
            $criticalIssues++;
        } elseif ($metrics['execution_time'] > $this->healthThresholds['execution_time'] * 0.8) {
            $warningIssues++;
        }

        // Check error rate
        $totalRequests = max(1, $metrics['request_count']);
        $errorRate = $metrics['error_count'] / $totalRequests;

        if ($errorRate > $this->healthThresholds['error_rate']) {
            $criticalIssues++;
        } elseif ($errorRate > $this->healthThresholds['error_rate'] * 0.8) {
            $warningIssues++;
        }

        // Check recent critical errors
        $recentCriticalErrors = array_filter($errors, function ($error) {
            return $error['severity'] === 'critical' &&
                   Carbon::parse($error['timestamp'])->isAfter(now()->subMinutes(30));
        });

        if (count($recentCriticalErrors) > 0) {
            $criticalIssues++;
        }

        if ($criticalIssues > 0) {
            return 'critical';
        } elseif ($warningIssues > 0) {
            return 'warning';
        }

        return 'healthy';
    }

    protected function generateRecommendations(array $metrics, array $errors): array
    {
        $recommendations = [];

        if ($metrics['memory_usage'] > $this->healthThresholds['memory_usage'] * 0.8) {
            $recommendations[] = 'Consider optimizing memory usage or increasing memory limits';
        }

        if ($metrics['execution_time'] > $this->healthThresholds['execution_time'] * 0.8) {
            $recommendations[] = 'Optimize slow operations or implement caching';
        }

        if (count($errors) > 5) {
            $recommendations[] = 'Review and fix recurring errors';
        }

        if ($metrics['database_queries'] > 50) {
            $recommendations[] = 'Consider optimizing database queries or implementing query caching';
        }

        return $recommendations;
    }

    protected function determineSeverity(\Throwable $exception): string
    {
        if ($exception instanceof \Error || $exception instanceof \ParseError) {
            return 'critical';
        }

        if ($exception instanceof \RuntimeException) {
            return 'warning';
        }

        return 'info';
    }

    protected function incrementMetric(string $pluginName, string $metric): void
    {
        $cacheKey = $this->cachePrefix . 'metrics_' . $pluginName;
        $metrics = Cache::get($cacheKey, []);

        $metrics[$metric] = ($metrics[$metric] ?? 0) + 1;

        Cache::put($cacheKey, $metrics, now()->addHours(24));
    }

    protected function isPluginEnabled(string $pluginName): bool
    {
        return (bool)config("{$pluginName}.enabled", true);
    }
}
