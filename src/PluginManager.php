<?php

namespace SoysalTan\LaravelPluginSystem;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

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
        $this->callLifecycleHook('onRegister');
    }

    public function boot(): void
    {
        $this->registerPluginViews();
        $this->registerPluginRoutes();
        $this->registerPluginControllers();
        $this->registerPluginCommands();
        $this->registerPluginEvents();
        $this->callLifecycleHook('onBoot');
    }

    protected function callLifecycleHook(string $method): void
    {
        if (!File::exists($this->pluginsPath)) {
            return;
        }

        $pluginDirectories = File::directories($this->pluginsPath);

        foreach ($pluginDirectories as $pluginDir) {
            $pluginName = basename($pluginDir);

            if (!$this->isPluginEnabled($pluginName)) {
                continue;
            }

            $serviceProviderPath = $pluginDir.'/ServiceProvider.php';

            if (File::exists($serviceProviderPath)) {
                $namespace = $this->getPluginNamespace($pluginName).'\\ServiceProvider';

                if (class_exists($namespace)) {
                    $serviceProvider = new $namespace($this->app);

                    if (method_exists($serviceProvider, $method)) {
                        $serviceProvider->$method();
                    }
                }
            }
        }
    }

    public function getRegisteredCommands(): array
    {
        return [
            Commands\MakePluginCommand::class,
            Commands\PluginHealthCommand::class,
        ];
    }

    protected function registerPluginCommands(): void
    {
        if (!File::exists($this->pluginsPath)) {
            return;
        }

        $pluginDirectories = File::directories($this->pluginsPath);

        foreach ($pluginDirectories as $pluginDir) {
            $pluginName = basename($pluginDir);

            if (!$this->isPluginEnabled($pluginName)) {
                continue;
            }

            $commandsPath = $pluginDir.'/Commands';

            if (File::exists($commandsPath)) {
                $commandFiles = File::files($commandsPath);

                foreach ($commandFiles as $commandFile) {
                    if ($commandFile->getExtension() !== 'php') {
                        continue;
                    }

                    $commandName = $commandFile->getFilenameWithoutExtension();
                    $namespace = $this->getPluginNamespace($pluginName)."\\Commands\\{$commandName}";

                    if (class_exists($namespace)) {
                        $commandInstance = $this->app->make($namespace);
                        $this->app['Illuminate\Contracts\Console\Kernel']->registerCommand($commandInstance);
                    }
                }
            }
        }
    }

    protected function registerPluginEvents(): void
    {
        if (!File::exists($this->pluginsPath)) {
            return;
        }

        $pluginDirectories = File::directories($this->pluginsPath);

        foreach ($pluginDirectories as $pluginDir) {
            $pluginName = basename($pluginDir);

            if (!$this->isPluginEnabled($pluginName)) {
                continue;
            }

            $eventsPath = $pluginDir.'/Events';
            $listenersPath = $pluginDir.'/Listeners';

            if (File::exists($eventsPath) && File::exists($listenersPath)) {
                $eventFiles = File::files($eventsPath);
                $listenerFiles = File::files($listenersPath);

                foreach ($eventFiles as $eventFile) {
                    if ($eventFile->getExtension() !== 'php') {
                        continue;
                    }

                    $eventName = $eventFile->getFilenameWithoutExtension();
                    $eventNamespace = $this->getPluginNamespace($pluginName)."\\Events\\{$eventName}";

                    foreach ($listenerFiles as $listenerFile) {
                        if ($listenerFile->getExtension() !== 'php') {
                            continue;
                        }

                        $listenerName = $listenerFile->getFilenameWithoutExtension();
                        $listenerNamespace = $this->getPluginNamespace($pluginName)."\\Listeners\\{$listenerName}";

                        if (class_exists($eventNamespace) && class_exists($listenerNamespace)) {
                            Event::listen($eventNamespace, $listenerNamespace);
                        }
                    }
                }
            }
        }
    }

    protected function registerPluginServices(): void
    {
        if (!File::exists($this->pluginsPath)) {
            return;
        }

        $pluginDirectories = File::directories($this->pluginsPath);

        foreach ($pluginDirectories as $pluginDir) {
            $pluginName = basename($pluginDir);

            if (!$this->isPluginEnabled($pluginName)) {
                continue;
            }

            $servicesPath = $pluginDir.'/Services';

            if (File::exists($servicesPath)) {
                $serviceFiles = File::files($servicesPath);

                foreach ($serviceFiles as $serviceFile) {
                    if ($serviceFile->getExtension() !== 'php') {
                        continue;
                    }

                    $pluginName = basename($pluginDir);
                    $serviceName = $serviceFile->getFilenameWithoutExtension();
                    $namespace = $this->getPluginNamespace($pluginName)."\\Services\\{$serviceName}";

                    if (class_exists($namespace)) {
                        $this->app->singleton($namespace, $namespace);

                        $interfaceName = $this->getPluginNamespace($pluginName)."\\Services\\{$serviceName}Interface";

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
            $pluginName = basename($pluginDir);

            if (!$this->isPluginEnabled($pluginName)) {
                continue;
            }

            $viewsPath = $pluginDir.'/Views';

            if (File::exists($viewsPath)) {
                $pluginName = strtolower($pluginName);

                View::addLocation($viewsPath);
                View::addNamespace("plugins.{$pluginName}", $viewsPath);

                if (config('laravel-plugin-system.enable_volt_support', true) && class_exists('Livewire\\Volt\\Volt')) {
                    call_user_func(['Livewire\\Volt\\Volt', 'mount'], $viewsPath);
                }
            }
        }
    }

    protected function isPluginEnabled(string $pluginName): bool
    {
        return (bool) config("{$pluginName}.enabled");
    }

    protected function registerPluginConfigs(): void
    {
        if (!File::exists($this->pluginsPath)) {
            return;
        }

        $pluginDirectories = File::directories($this->pluginsPath);

        foreach ($pluginDirectories as $pluginDir) {
            $configFile = $pluginDir.'/config.php';

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
            $pluginName = basename($pluginDir);

            if (!$this->isPluginEnabled($pluginName)) {
                continue;
            }

            $routesFile = $pluginDir.'/routes.php';

            if (File::exists($routesFile)) {
                $pluginName = strtolower($pluginName);

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
            $pluginName = basename($pluginDir);

            if (!$this->isPluginEnabled($pluginName)) {
                continue;
            }

            $controllersPath = $pluginDir.'/Controllers';

            if (File::exists($controllersPath)) {
                $controllerFiles = File::files($controllersPath);

                foreach ($controllerFiles as $controllerFile) {
                    if ($controllerFile->getExtension() !== 'php') {
                        continue;
                    }

                    $controllerName = $controllerFile->getFilenameWithoutExtension();
                    $namespace = $this->getPluginNamespace($pluginName)."\\Controllers\\{$controllerName}";

                    if (class_exists($namespace)) {
                        $this->app->bind($namespace, $namespace);
                    }
                }
            }
        }
    }

    protected function getPluginNamespace(string $pluginName): string
    {
        return config('laravel-plugin-system.plugin_namespace', 'App\\Plugins')."\\{$pluginName}";
    }
}
