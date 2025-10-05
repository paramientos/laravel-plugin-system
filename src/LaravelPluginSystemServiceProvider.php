<?php

namespace SoysalTan\LaravelPluginSystem;

use Illuminate\Support\ServiceProvider;

class LaravelPluginSystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-plugin-system.php',
            'laravel-plugin-system'
        );

        $this->app->singleton(PluginManager::class);

        $pluginManager = $this->app->make(PluginManager::class);
        $pluginManager->register();

        $this->commands($pluginManager->registerPluginCommands());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-plugin-system.php' => config_path('laravel-plugin-system.php'),
        ], 'laravel-plugin-system-config');

        $pluginManager = $this->app->make(PluginManager::class);
        $pluginManager->boot();
    }
}
