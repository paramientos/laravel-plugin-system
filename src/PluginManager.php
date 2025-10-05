<?php

namespace SoysalTan\LaravelPluginSystem;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Volt\Volt;

class PluginManager
{
    public static bool $usePluginsPrefixInRoutes = false;
    
    protected string $pluginsPath;
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->pluginsPath = config('laravel-plugin-system.plugins_path', app_path('Plugins'));
    }

    public function register(): void
    {
        $this->registerPluginConfigs();
        $this->registerPluginServices();
    }

    public function boot(): void
    {
        $this->registerPluginViews();
        $this->registerPluginRoutes();
        $this->registerPluginControllers();
    }

    public function registerPluginCommands(): array
    {
        return [
            Commands\MakePluginCommand::class,
        ];
    }

    protected function registerPluginServices(): void
    {
        if (!File::exists($this->pluginsPath)) {
            return;
        }

        $pluginDirectories = File::directories($this->pluginsPath);

        foreach ($pluginDirectories as $pluginDir) {
            $servicesPath = $pluginDir . '/Services';

            if (File::exists($servicesPath)) {
                $serviceFiles = File::files($servicesPath);

                foreach ($serviceFiles as $serviceFile) {
                    if ($serviceFile->getExtension() !== 'php') {
                        continue;
                    }

                    $pluginName = basename($pluginDir);
                    $serviceName = $serviceFile->getFilenameWithoutExtension();
                    $namespace = $this->getPluginNamespace($pluginName) . "\\Services\\{$serviceName}";

                    if (class_exists($namespace)) {
                        $this->app->singleton($namespace, $namespace);

                        $interfaceName = $this->getPluginNamespace($pluginName) . "\\Services\\{$serviceName}Interface";

                        if (interface_exists($interfaceName)) {
                            $this->app->bind($interfaceName, $namespace);
                        }
                    }
                }
            }
        }
    }

    protected function registerPluginViews(): void
    {
        if (!File::exists($this->pluginsPath)) {
            return;
        }

        $pluginDirectories = File::directories($this->pluginsPath);

        foreach ($pluginDirectories as $pluginDir) {
            $viewsPath = $pluginDir . '/Views';

            if (File::exists($viewsPath)) {
                $pluginName = strtolower(basename($pluginDir));

                View::addLocation($viewsPath);
                View::addNamespace("plugins.{$pluginName}", $viewsPath);

                // Only mount Volt if enabled and Volt class exists
                if (config('laravel-plugin-system.enable_volt_support', true) && class_exists('Livewire\Volt\Volt')) {
                    Volt::mount($viewsPath);
                }
            }
        }
    }

    protected function registerPluginConfigs(): void
    {
        if (!File::exists($this->pluginsPath)) {
            return;
        }

        $pluginDirectories = File::directories($this->pluginsPath);

        foreach ($pluginDirectories as $pluginDir) {
            $configFile = $pluginDir . '/config.php';

            if (File::exists($configFile)) {
                $pluginName = basename($pluginDir);
                $config = require $configFile;

                if (is_array($config)) {
                    config(["{$pluginName}" => $config]);
                }
            }
        }
    }

    protected function registerPluginRoutes(): void
    {
        if (!File::exists($this->pluginsPath)) {
            return;
        }

        $pluginDirectories = File::directories($this->pluginsPath);

        foreach ($pluginDirectories as $pluginDir) {
            $routesFile = $pluginDir . '/routes.php';

            if (File::exists($routesFile)) {
                $pluginName = strtolower(basename($pluginDir));

                $prefix = self::$usePluginsPrefixInRoutes
                    ? "plugins/{$pluginName}"
                    : $pluginName;

                Route::prefix($prefix)
                    ->name("plugins.{$pluginName}.")
                    ->group($routesFile);
            }
        }
    }

    protected function registerPluginControllers(): void
    {
        if (!File::exists($this->pluginsPath)) {
            return;
        }

        $pluginDirectories = File::directories($this->pluginsPath);

        foreach ($pluginDirectories as $pluginDir) {
            $controllersPath = $pluginDir . '/Controllers';

            if (File::exists($controllersPath)) {
                $controllerFiles = File::files($controllersPath);

                foreach ($controllerFiles as $controllerFile) {
                    if ($controllerFile->getExtension() !== 'php') {
                        continue;
                    }

                    $pluginName = basename($pluginDir);
                    $controllerName = $controllerFile->getFilenameWithoutExtension();
                    $namespace = $this->getPluginNamespace($pluginName) . "\\Controllers\\{$controllerName}";

                    if (class_exists($namespace)) {
                        $this->app->bind($namespace, $namespace);
                    }
                }
            }
        }
    }

    protected function getPluginNamespace(string $pluginName): string
    {
        return config('laravel-plugin-system.plugin_namespace', 'App\\Plugins') . "\\{$pluginName}";
    }
}
