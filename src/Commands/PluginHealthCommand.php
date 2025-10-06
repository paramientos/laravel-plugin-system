<?php

namespace SoysalTan\LaravelPluginSystem\Commands;

use Illuminate\Console\Command;
use SoysalTan\LaravelPluginSystem\Services\PluginHealthMonitor;
use Symfony\Component\Console\Command\Command as CommandAlias;

class PluginHealthCommand extends Command
{
    protected $signature = 'plugin:health
                            {plugin? : Specific plugin name to check}
                            {--watch : Watch mode - continuously monitor health}
                            {--json : Output in JSON format}
                            {--detailed : Show detailed metrics}
                            {--errors : Show recent errors}
                            {--clear-errors= : Clear errors for specific plugin}';

    protected $description = 'Monitor plugin health status and metrics';

    protected PluginHealthMonitor $healthMonitor;

    public function __construct(PluginHealthMonitor $healthMonitor)
    {
        parent::__construct();
        $this->healthMonitor = $healthMonitor;
    }

    public function handle(): int
    {
        if ($this->option('clear-errors')) {
            return $this->clearErrors();
        }

        if ($this->option('watch')) {
            return $this->watchMode();
        }

        $pluginName = $this->argument('plugin');

        if ($pluginName) {
            return $this->showPluginHealth($pluginName);
        }

        return $this->showAllPluginsHealth();
    }

    protected function showPluginHealth(string $pluginName): int
    {
        $health = $this->healthMonitor->checkPluginHealth($pluginName);

        if (empty($health)) {
            $this->error("Plugin '{$pluginName}' not found or not enabled.");

            return CommandAlias::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($health, JSON_PRETTY_PRINT));

            return CommandAlias::SUCCESS;
        }

        $this->displayPluginHealth($health);

        return CommandAlias::SUCCESS;
    }

    protected function showAllPluginsHealth(): int
    {
        $healthReport = $this->healthMonitor->getHealthReport();

        if ($this->option('json')) {
            $this->line(json_encode($healthReport, JSON_PRETTY_PRINT));

            return CommandAlias::SUCCESS;
        }

        $this->displayHealthSummary($healthReport['summary']);
        $this->newLine();

        foreach ($healthReport['plugins'] as $pluginHealth) {
            $this->displayPluginHealth($pluginHealth, false);
            $this->newLine();
        }

        return CommandAlias::SUCCESS;
    }

    protected function displayHealthSummary(array $summary): void
    {
        $this->info('=== Plugin Health Summary ===');

        $statusColor = match ($summary['overall_status']) {
            'healthy' => 'green',
            'warning' => 'yellow',
            'critical' => 'red',
            default => 'white'
        };

        $this->line("Overall Status: <fg={$statusColor}>".strtoupper($summary['overall_status']).'</>');
        $this->line("Total Plugins: {$summary['total_plugins']}");
        $this->line("<fg=green>Healthy: {$summary['healthy_plugins']}</>");
        $this->line("<fg=yellow>Warning: {$summary['warning_plugins']}</>");
        $this->line("<fg=red>Critical: {$summary['critical_plugins']}</>");
        $this->line("Last Check: {$summary['last_check']}");
    }

    protected function displayPluginHealth(array $health, ?bool $detailed = null): void
    {
        $detailed = $detailed ?? $this->option('detailed');

        $statusColor = match ($health['status']) {
            'healthy' => 'green',
            'warning' => 'yellow',
            'critical' => 'red',
            default => 'white'
        };

        $this->line("Plugin: <fg=cyan>{$health['plugin_name']}</>");
        $this->line("Status: <fg={$statusColor}>".strtoupper($health['status']).'</>');
        $this->line("Uptime: {$health['uptime']}%");

        if ($detailed) {
            $this->displayDetailedMetrics($health['metrics']);
        }

        if ($this->option('errors') && !empty($health['recent_errors'])) {
            $this->displayRecentErrors($health['recent_errors']);
        }

        if (!empty($health['recommendations'])) {
            $this->displayRecommendations($health['recommendations']);
        }
    }

    protected function displayDetailedMetrics(array $metrics): void
    {
        $this->line('Metrics:');
        $this->line('  Memory Usage: '.$this->formatBytes($metrics['memory_usage'] ?? 0));
        $this->line('  Execution Time: '.($metrics['execution_time'] ?? 0).'ms');
        $this->line('  Request Count: '.($metrics['request_count'] ?? 0));
        $this->line('  Error Count: '.($metrics['error_count'] ?? 0));
        $this->line('  Response Time: '.($metrics['response_time'] ?? 0).'ms');
        $this->line('  Database Queries: '.($metrics['database_queries'] ?? 0));
        $this->line('  Cache Hits: '.($metrics['cache_hits'] ?? 0));
        $this->line('  Cache Misses: '.($metrics['cache_misses'] ?? 0));

        if ($metrics['last_activity']) {
            $this->line("  Last Activity: {$metrics['last_activity']}");
        }
    }

    protected function displayRecentErrors(array $errors): void
    {
        $this->line('<fg=red>Recent Errors:</>');

        foreach ($errors as $index => $error) {
            $severityColor = match ($error['severity']) {
                'critical' => 'red',
                'warning' => 'yellow',
                'info' => 'blue',
                default => 'white'
            };

            $this->line('  '.($index + 1).". <fg={$severityColor}>[{$error['severity']}]</> {$error['message']}");
            $this->line("     File: {$error['file']}:{$error['line']}");
            $this->line("     Time: {$error['timestamp']}");
        }
    }

    protected function displayRecommendations(array $recommendations): void
    {
        $this->line('<fg=yellow>Recommendations:</>');

        foreach ($recommendations as $index => $recommendation) {
            $this->line('  '.($index + 1).". {$recommendation}");
        }
    }

    protected function watchMode(): int
    {
        $this->info('Starting health monitoring in watch mode. Press Ctrl+C to stop.');

        try {
            while (true) {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    system('cls');
                } else {
                    system('clear');
                }

                $this->line('Plugin Health Monitor - '.now()->format('Y-m-d H:i:s'));
                $this->line(str_repeat('=', 60));

                $this->showAllPluginsHealth();

                sleep(5); // Refresh every 5 seconds
            }
        } catch (\Exception $e) {
            $this->error('Watch mode interrupted: '.$e->getMessage());

            return CommandAlias::FAILURE;
        }

        return CommandAlias::SUCCESS;
    }

    protected function clearErrors(): int
    {
        $pluginName = $this->option('clear-errors');

        if ($pluginName === 'all') {
            // Clear errors for all plugins
            $healthReport = $this->healthMonitor->getHealthReport();

            foreach ($healthReport['plugins'] as $plugin) {
                $this->healthMonitor->clearPluginErrors($plugin['plugin_name']);
            }

            $this->info('Cleared errors for all plugins.');
        } else {
            $this->healthMonitor->clearPluginErrors($pluginName);
            $this->info("Cleared errors for plugin: {$pluginName}");
        }

        return CommandAlias::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
