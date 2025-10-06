<?php

namespace SoysalTan\LaravelPluginSystem\Contracts;

interface PluginHealthMonitorInterface
{
    public function checkPluginHealth(string $pluginName): array;
    
    public function checkAllPluginsHealth(): array;
    
    public function getPluginMetrics(string $pluginName): array;
    
    public function isPluginHealthy(string $pluginName): bool;
    
    public function getHealthThresholds(): array;
    
    public function setHealthThreshold(string $metric, $value): void;
    
    public function getPluginErrors(string $pluginName, int $limit = 10): array;
    
    public function clearPluginErrors(string $pluginName): void;
    
    public function getPluginUptime(string $pluginName): float;
    
    public function recordPluginError(string $pluginName, \Throwable $exception): void;
    
    public function recordPluginMetric(string $pluginName, string $metric, $value): void;
    
    public function getHealthReport(): array;
}