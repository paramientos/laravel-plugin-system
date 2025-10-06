<?php

namespace SoysalTan\LaravelPluginSystem\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PluginLogViewerCommand extends Command
{
    protected $signature = 'plugin:logs 
                            {plugin? : Plugin name to view logs for}
                            {--level=* : Log levels to filter (debug, info, warning, error, critical)}
                            {--tail : Follow log file in real-time}
                            {--lines=50 : Number of lines to show}
                            {--since= : Show logs since date (Y-m-d H:i:s)}
                            {--until= : Show logs until date (Y-m-d H:i:s)}
                            {--search= : Search for specific text in logs}
                            {--format=table : Output format (table, json, raw)}
                            {--export= : Export logs to file}
                            {--clear : Clear plugin logs}';

    protected $description = 'View and manage plugin-specific logs with filtering and real-time monitoring';

    protected array $logLevels = ['debug', 'info', 'warning', 'error', 'critical'];
    protected array $logColors = [
        'debug' => 'gray',
        'info' => 'blue',
        'warning' => 'yellow',
        'error' => 'red',
        'critical' => 'magenta'
    ];

    public function handle()
    {
        $plugin = $this->argument('plugin');
        
        if ($this->option('clear')) {
            return $this->clearLogs($plugin);
        }

        if ($this->option('tail')) {
            return $this->tailLogs($plugin);
        }

        return $this->viewLogs($plugin);
    }

    protected function viewLogs(?string $plugin): int
    {
        $logs = $this->collectLogs($plugin);
        
        if (empty($logs)) {
            $this->info('No logs found for the specified criteria.');
            return 0;
        }

        $format = $this->option('format');
        
        if ($this->option('export')) {
            return $this->exportLogs($logs, $this->option('export'));
        }

        switch ($format) {
            case 'json':
                $this->line(json_encode($logs, JSON_PRETTY_PRINT));
                break;
            case 'raw':
                foreach ($logs as $log) {
                    $this->line($log['raw']);
                }
                break;
            default:
                $this->displayLogsTable($logs);
        }

        return 0;
    }

    protected function tailLogs(?string $plugin): int
    {
        $this->info('Following logs in real-time. Press Ctrl+C to stop.');
        
        $logFiles = $this->getLogFiles($plugin);
        $lastPositions = [];
        
        foreach ($logFiles as $file) {
            $lastPositions[$file] = filesize($file);
        }

        try {
            while (true) {
                foreach ($logFiles as $file) {
                    $currentSize = filesize($file);
                    
                    if ($currentSize > $lastPositions[$file]) {
                        $handle = fopen($file, 'r');
                        fseek($handle, $lastPositions[$file]);
                        
                        while (($line = fgets($handle)) !== false) {
                            $logEntry = $this->parseLogLine($line, $file);
                            if ($logEntry && $this->matchesFilters($logEntry)) {
                                $this->displayLogEntry($logEntry);
                            }
                        }
                        
                        fclose($handle);
                        $lastPositions[$file] = $currentSize;
                    }
                }
                
                usleep(500000); // 0.5 second
            }
        } catch (\Exception $e) {
            $this->error('Log tailing interrupted: ' . $e->getMessage());
        }

        return 0;
    }

    protected function clearLogs(?string $plugin): int
    {
        $logFiles = $this->getLogFiles($plugin);
        $cleared = 0;

        foreach ($logFiles as $file) {
            if (File::exists($file)) {
                File::put($file, '');
                $cleared++;
            }
        }

        $pluginText = $plugin ? "plugin '{$plugin}'" : 'all plugins';
        $this->info("Cleared {$cleared} log files for {$pluginText}.");

        return 0;
    }

    protected function collectLogs(?string $plugin): array
    {
        $logs = [];
        $logFiles = $this->getLogFiles($plugin);
        $lines = (int) $this->option('lines');

        foreach ($logFiles as $file) {
            if (!File::exists($file)) {
                continue;
            }

            $fileLines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $fileLines = array_slice($fileLines, -$lines);

            foreach ($fileLines as $line) {
                $logEntry = $this->parseLogLine($line, $file);
                if ($logEntry && $this->matchesFilters($logEntry)) {
                    $logs[] = $logEntry;
                }
            }
        }

        usort($logs, function ($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        return array_slice($logs, -$lines);
    }

    protected function getLogFiles(?string $plugin): array
    {
        $logFiles = [];
        
        if ($plugin) {
            $pluginLogFile = storage_path("logs/plugins/{$plugin}.log");
            if (File::exists($pluginLogFile)) {
                $logFiles[] = $pluginLogFile;
            }
        } else {
            $pluginLogsDir = storage_path('logs/plugins');
            if (File::exists($pluginLogsDir)) {
                $logFiles = File::glob($pluginLogsDir . '/*.log');
            }
        }

        $laravelLog = storage_path('logs/laravel.log');
        if (File::exists($laravelLog)) {
            $logFiles[] = $laravelLog;
        }

        return $logFiles;
    }

    protected function parseLogLine(string $line, string $file): ?array
    {
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)$/';
        
        if (preg_match($pattern, $line, $matches)) {
            return [
                'timestamp' => Carbon::parse($matches[1]),
                'environment' => $matches[2],
                'level' => strtolower($matches[3]),
                'message' => $matches[4],
                'file' => basename($file),
                'raw' => $line
            ];
        }

        return null;
    }

    protected function matchesFilters(array $logEntry): bool
    {
        $levels = $this->option('level');
        if (!empty($levels) && !in_array($logEntry['level'], $levels)) {
            return false;
        }

        $since = $this->option('since');
        if ($since && $logEntry['timestamp']->lt(Carbon::parse($since))) {
            return false;
        }

        $until = $this->option('until');
        if ($until && $logEntry['timestamp']->gt(Carbon::parse($until))) {
            return false;
        }

        $search = $this->option('search');
        if ($search && stripos($logEntry['message'], $search) === false) {
            return false;
        }

        return true;
    }

    protected function displayLogsTable(array $logs): void
    {
        $headers = ['Time', 'Level', 'File', 'Message'];
        $rows = [];

        foreach ($logs as $log) {
            $rows[] = [
                $log['timestamp']->format('H:i:s'),
                $this->colorizeLevel($log['level']),
                $log['file'],
                $this->truncateMessage($log['message'], 80)
            ];
        }

        $this->table($headers, $rows);
    }

    protected function displayLogEntry(array $logEntry): void
    {
        $time = $logEntry['timestamp']->format('H:i:s');
        $level = $this->colorizeLevel($logEntry['level']);
        $file = $logEntry['file'];
        $message = $logEntry['message'];

        $this->line("[{$time}] {$level} {$file}: {$message}");
    }

    protected function colorizeLevel(string $level): string
    {
        $color = $this->logColors[$level] ?? 'white';
        return "<fg={$color}>" . strtoupper($level) . "</fg={$color}>";
    }

    protected function truncateMessage(string $message, int $length): string
    {
        return strlen($message) > $length ? substr($message, 0, $length) . '...' : $message;
    }

    protected function exportLogs(array $logs, string $filename): int
    {
        $content = '';
        
        foreach ($logs as $log) {
            $content .= $log['raw'] . "\n";
        }

        File::put($filename, $content);
        $this->info("Logs exported to: {$filename}");

        return 0;
    }
}