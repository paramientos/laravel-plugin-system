<?php

namespace SoysalTan\LaravelPluginSystem\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PluginProfilingService
{
    protected array $profiles = [];
    protected array $activeProfiles = [];
    protected string $cachePrefix = 'plugin_profiling_';

    public function startProfiling(string $pluginName, array $options = []): string
    {
        $profileId = uniqid('profile_');
        
        $this->activeProfiles[$profileId] = [
            'plugin_name' => $pluginName,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true),
            'options' => $options,
            'queries' => [],
            'events' => [],
            'checkpoints' => []
        ];

        if ($options['track_queries'] ?? false) {
            DB::enableQueryLog();
        }

        return $profileId;
    }

    public function stopProfiling(string $profileId): array
    {
        if (!isset($this->activeProfiles[$profileId])) {
            throw new \InvalidArgumentException("Profile ID '{$profileId}' not found");
        }

        $profile = $this->activeProfiles[$profileId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $result = [
            'profile_id' => $profileId,
            'plugin_name' => $profile['plugin_name'],
            'execution_time' => round(($endTime - $profile['start_time']) * 1000, 2),
            'memory_usage' => [
                'start' => $profile['start_memory'],
                'end' => $endMemory,
                'peak' => $peakMemory,
                'difference' => $endMemory - $profile['start_memory']
            ],
            'queries' => $this->getQueryData($profile['options']),
            'events' => $profile['events'],
            'checkpoints' => $profile['checkpoints'],
            'performance_score' => $this->calculatePerformanceScore($profile, $endTime, $endMemory),
            'recommendations' => $this->generateRecommendations($profile, $endTime, $endMemory),
            'timestamp' => now()->toISOString()
        ];

        $this->profiles[$profileId] = $result;
        unset($this->activeProfiles[$profileId]);

        $this->cacheProfile($result);

        return $result;
    }

    public function addCheckpoint(string $profileId, string $name, array $data = []): void
    {
        if (!isset($this->activeProfiles[$profileId])) {
            return;
        }

        $this->activeProfiles[$profileId]['checkpoints'][] = [
            'name' => $name,
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
            'data' => $data,
            'timestamp' => now()->toISOString()
        ];
    }

    public function addEvent(string $profileId, string $event, array $data = []): void
    {
        if (!isset($this->activeProfiles[$profileId])) {
            return;
        }

        $this->activeProfiles[$profileId]['events'][] = [
            'event' => $event,
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
            'data' => $data,
            'timestamp' => now()->toISOString()
        ];
    }

    public function getProfile(string $profileId): ?array
    {
        if (isset($this->profiles[$profileId])) {
            return $this->profiles[$profileId];
        }

        return $this->getCachedProfile($profileId);
    }

    public function getPluginProfiles(string $pluginName, int $limit = 10): array
    {
        $cacheKey = $this->cachePrefix . 'plugin_' . $pluginName;
        $profiles = Cache::get($cacheKey, []);

        return array_slice($profiles, -$limit);
    }

    public function analyzePerformance(string $pluginName, int $days = 7): array
    {
        $profiles = $this->getPluginProfiles($pluginName, 1000);
        $recentProfiles = collect($profiles)
            ->filter(fn($profile) => 
                now()->diffInDays($profile['timestamp']) <= $days
            );

        if ($recentProfiles->isEmpty()) {
            return [
                'plugin_name' => $pluginName,
                'analysis_period' => $days,
                'total_profiles' => 0,
                'message' => 'No profiling data available for the specified period'
            ];
        }

        $executionTimes = $recentProfiles->pluck('execution_time');
        $memoryUsages = $recentProfiles->pluck('memory_usage.peak');
        $queryCounts = $recentProfiles->pluck('queries.count');

        return [
            'plugin_name' => $pluginName,
            'analysis_period' => $days,
            'total_profiles' => $recentProfiles->count(),
            'execution_time' => [
                'average' => round($executionTimes->avg(), 2),
                'min' => $executionTimes->min(),
                'max' => $executionTimes->max(),
                'median' => $executionTimes->median(),
                'percentile_95' => $executionTimes->percentile(95)
            ],
            'memory_usage' => [
                'average' => round($memoryUsages->avg()),
                'min' => $memoryUsages->min(),
                'max' => $memoryUsages->max(),
                'median' => $memoryUsages->median()
            ],
            'database_queries' => [
                'average' => round($queryCounts->avg(), 2),
                'min' => $queryCounts->min(),
                'max' => $queryCounts->max(),
                'total' => $queryCounts->sum()
            ],
            'performance_trends' => $this->analyzePerformanceTrends($recentProfiles),
            'bottlenecks' => $this->identifyBottlenecks($recentProfiles),
            'recommendations' => $this->generateAnalysisRecommendations($recentProfiles)
        ];
    }

    public function comparePlugins(array $pluginNames, int $days = 7): array
    {
        $comparisons = [];

        foreach ($pluginNames as $pluginName) {
            $analysis = $this->analyzePerformance($pluginName, $days);
            $comparisons[$pluginName] = $analysis;
        }

        return [
            'comparison_date' => now()->toISOString(),
            'period_days' => $days,
            'plugins' => $comparisons,
            'rankings' => $this->rankPluginsByPerformance($comparisons),
            'insights' => $this->generateComparisonInsights($comparisons)
        ];
    }

    public function detectAnomalies(string $pluginName, int $days = 7): array
    {
        $profiles = $this->getPluginProfiles($pluginName, 1000);
        $recentProfiles = collect($profiles)
            ->filter(fn($profile) => 
                now()->diffInDays($profile['timestamp']) <= $days
            );

        if ($recentProfiles->count() < 10) {
            return [
                'plugin_name' => $pluginName,
                'anomalies' => [],
                'message' => 'Insufficient data for anomaly detection'
            ];
        }

        $anomalies = [];
        $executionTimes = $recentProfiles->pluck('execution_time');
        $memoryUsages = $recentProfiles->pluck('memory_usage.peak');

        $avgExecutionTime = $executionTimes->avg();
        $stdExecutionTime = $this->calculateStandardDeviation($executionTimes->toArray());
        
        $avgMemoryUsage = $memoryUsages->avg();
        $stdMemoryUsage = $this->calculateStandardDeviation($memoryUsages->toArray());

        foreach ($recentProfiles as $profile) {
            $executionAnomaly = abs($profile['execution_time'] - $avgExecutionTime) > (2 * $stdExecutionTime);
            $memoryAnomaly = abs($profile['memory_usage']['peak'] - $avgMemoryUsage) > (2 * $stdMemoryUsage);

            if ($executionAnomaly || $memoryAnomaly) {
                $anomalies[] = [
                    'profile_id' => $profile['profile_id'],
                    'timestamp' => $profile['timestamp'],
                    'type' => $executionAnomaly ? 'execution_time' : 'memory_usage',
                    'value' => $executionAnomaly ? $profile['execution_time'] : $profile['memory_usage']['peak'],
                    'expected_range' => $executionAnomaly 
                        ? [$avgExecutionTime - (2 * $stdExecutionTime), $avgExecutionTime + (2 * $stdExecutionTime)]
                        : [$avgMemoryUsage - (2 * $stdMemoryUsage), $avgMemoryUsage + (2 * $stdMemoryUsage)],
                    'severity' => $this->calculateAnomalySeverity($profile, $avgExecutionTime, $avgMemoryUsage)
                ];
            }
        }

        return [
            'plugin_name' => $pluginName,
            'analysis_period' => $days,
            'total_profiles_analyzed' => $recentProfiles->count(),
            'anomalies_detected' => count($anomalies),
            'anomalies' => $anomalies
        ];
    }

    protected function getQueryData(array $options): array
    {
        if (!($options['track_queries'] ?? false)) {
            return ['count' => 0, 'queries' => []];
        }

        $queries = DB::getQueryLog();
        $slowQueryThreshold = $options['slow_query_threshold'] ?? 1000;

        return [
            'count' => count($queries),
            'total_time' => collect($queries)->sum('time'),
            'slow_queries' => collect($queries)
                ->filter(fn($query) => $query['time'] > $slowQueryThreshold)
                ->values()
                ->toArray(),
            'queries' => $queries
        ];
    }

    protected function calculatePerformanceScore(array $profile, float $endTime, int $endMemory): int
    {
        $executionTime = ($endTime - $profile['start_time']) * 1000;
        $memoryUsage = $endMemory - $profile['start_memory'];
        
        $timeScore = max(0, 100 - ($executionTime / 10));
        $memoryScore = max(0, 100 - ($memoryUsage / (1024 * 1024)));
        
        return (int) round(($timeScore + $memoryScore) / 2);
    }

    protected function generateRecommendations(array $profile, float $endTime, int $endMemory): array
    {
        $recommendations = [];
        $executionTime = ($endTime - $profile['start_time']) * 1000;
        $memoryUsage = $endMemory - $profile['start_memory'];

        if ($executionTime > 1000) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'message' => 'Execution time is high. Consider optimizing database queries or caching results.',
                'metric' => 'execution_time',
                'value' => $executionTime
            ];
        }

        if ($memoryUsage > 50 * 1024 * 1024) {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'medium',
                'message' => 'High memory usage detected. Consider optimizing data structures or implementing pagination.',
                'metric' => 'memory_usage',
                'value' => $memoryUsage
            ];
        }

        if (count($profile['events']) > 100) {
            $recommendations[] = [
                'type' => 'events',
                'priority' => 'low',
                'message' => 'High number of events detected. Consider reducing event frequency or batching.',
                'metric' => 'event_count',
                'value' => count($profile['events'])
            ];
        }

        return $recommendations;
    }

    protected function analyzePerformanceTrends(object $profiles): array
    {
        $sortedProfiles = $profiles->sortBy('timestamp');
        $executionTimes = $sortedProfiles->pluck('execution_time')->toArray();
        
        if (count($executionTimes) < 2) {
            return ['trend' => 'insufficient_data'];
        }

        $slope = $this->calculateTrendSlope($executionTimes);
        
        return [
            'trend' => $slope > 0.1 ? 'degrading' : ($slope < -0.1 ? 'improving' : 'stable'),
            'slope' => $slope,
            'confidence' => $this->calculateTrendConfidence($executionTimes)
        ];
    }

    protected function identifyBottlenecks(object $profiles): array
    {
        $bottlenecks = [];
        
        $avgExecutionTime = $profiles->avg('execution_time');
        $slowProfiles = $profiles->filter(fn($p) => $p['execution_time'] > $avgExecutionTime * 1.5);
        
        if ($slowProfiles->isNotEmpty()) {
            $bottlenecks[] = [
                'type' => 'slow_execution',
                'affected_profiles' => $slowProfiles->count(),
                'average_time' => $slowProfiles->avg('execution_time'),
                'description' => 'Some executions are significantly slower than average'
            ];
        }

        return $bottlenecks;
    }

    protected function generateAnalysisRecommendations(object $profiles): array
    {
        $recommendations = [];
        $avgExecutionTime = $profiles->avg('execution_time');
        $avgMemoryUsage = $profiles->avg('memory_usage.peak');

        if ($avgExecutionTime > 500) {
            $recommendations[] = 'Consider implementing caching to reduce execution time';
        }

        if ($avgMemoryUsage > 100 * 1024 * 1024) {
            $recommendations[] = 'High memory usage detected, consider optimizing data handling';
        }

        return $recommendations;
    }

    protected function rankPluginsByPerformance(array $comparisons): array
    {
        $rankings = [];
        
        foreach ($comparisons as $pluginName => $analysis) {
            if ($analysis['total_profiles'] > 0) {
                $score = $this->calculateOverallScore($analysis);
                $rankings[$pluginName] = $score;
            }
        }

        arsort($rankings);
        
        return $rankings;
    }

    protected function generateComparisonInsights(array $comparisons): array
    {
        $insights = [];
        
        $executionTimes = collect($comparisons)
            ->pluck('execution_time.average')
            ->filter();
            
        if ($executionTimes->isNotEmpty()) {
            $fastest = $executionTimes->min();
            $slowest = $executionTimes->max();
            
            $insights[] = "Performance varies by " . round((($slowest - $fastest) / $fastest) * 100, 1) . "%";
        }

        return $insights;
    }

    protected function calculateOverallScore(array $analysis): float
    {
        if ($analysis['total_profiles'] === 0) {
            return 0;
        }

        $timeScore = max(0, 100 - ($analysis['execution_time']['average'] / 10));
        $memoryScore = max(0, 100 - ($analysis['memory_usage']['average'] / (1024 * 1024)));
        
        return ($timeScore + $memoryScore) / 2;
    }

    protected function calculateStandardDeviation(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
        
        return sqrt($variance);
    }

    protected function calculateTrendSlope(array $values): float
    {
        $n = count($values);
        $x = range(1, $n);
        
        $sumX = array_sum($x);
        $sumY = array_sum($values);
        $sumXY = array_sum(array_map(fn($i) => $x[$i] * $values[$i], range(0, $n - 1)));
        $sumX2 = array_sum(array_map(fn($val) => $val * $val, $x));
        
        return ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    }

    protected function calculateTrendConfidence(array $values): float
    {
        return min(1.0, count($values) / 20);
    }

    protected function calculateAnomalySeverity(array $profile, float $avgExecution, float $avgMemory): string
    {
        $executionDiff = abs($profile['execution_time'] - $avgExecution) / $avgExecution;
        $memoryDiff = abs($profile['memory_usage']['peak'] - $avgMemory) / $avgMemory;
        
        $maxDiff = max($executionDiff, $memoryDiff);
        
        if ($maxDiff > 3) return 'critical';
        if ($maxDiff > 2) return 'high';
        if ($maxDiff > 1) return 'medium';
        
        return 'low';
    }

    protected function cacheProfile(array $profile): void
    {
        $pluginName = $profile['plugin_name'];
        $cacheKey = $this->cachePrefix . 'plugin_' . $pluginName;
        
        $profiles = Cache::get($cacheKey, []);
        $profiles[] = $profile;
        
        if (count($profiles) > 100) {
            $profiles = array_slice($profiles, -100);
        }
        
        Cache::put($cacheKey, $profiles, now()->addDays(30));
    }

    protected function getCachedProfile(string $profileId): ?array
    {
        try {
            $pluginsPath = config('laravel-plugin-system.plugins_path', app_path('Plugins'));
            $pluginDirectories = File::directories($pluginsPath);
            
            foreach ($pluginDirectories as $pluginDir) {
                $pluginName = basename($pluginDir);
                $cacheKey = $this->cachePrefix . 'plugin_' . $pluginName;
                $profiles = Cache::get($cacheKey, []);
                
                foreach ($profiles as $profile) {
                    if ($profile['profile_id'] === $profileId) {
                        return $profile;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Error retrieving cached profile: " . $e->getMessage());
        }
        
        return null;
    }
}