<?php

namespace SoysalTan\LaravelPluginSystem;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use SoysalTan\LaravelPluginSystem\Contracts\PluginHealthMonitorInterface;
use SoysalTan\LaravelPluginSystem\Middleware\PluginHealthCollector;
use SoysalTan\LaravelPluginSystem\Services\PluginHealthAlertSystem;
use SoysalTan\LaravelPluginSystem\Services\PluginHealthMonitor;

class LaravelPluginSystemServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-plugin-system.php',
            'laravel-plugin-system'
        );

        $this->app->singleton(PluginManager::class);

        // Register health monitoring services
        $this->app->bind(PluginHealthMonitorInterface::class, PluginHealthMonitor::class);
        $this->app->singleton(PluginHealthMonitor::class);
        $this->app->singleton(PluginHealthAlertSystem::class);
        $this->app->singleton(PluginHealthCollector::class);

        $pluginManager = $this->app->make(PluginManager::class);
        $pluginManager->register();

        $this->commands($pluginManager->getRegisteredCommands());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-plugin-system.php' => config_path('laravel-plugin-system.php'),
        ], 'laravel-plugin-system-config');

        $pluginManager = $this->app->make(PluginManager::class);
        $pluginManager->boot();

        // Register middleware if health monitoring is enabled
        $this->app['router']->aliasMiddleware('plugin.health', PluginHealthCollector::class);
    }
}
