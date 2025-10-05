<?php

namespace SoysalTan\LaravelPluginSystem\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use SoysalTan\LaravelPluginSystem\Tests\TestCase;

class PluginLifecycleTest extends TestCase
{
    public function test_complete_plugin_creation_and_registration_lifecycle()
    {
        $this->artisan('make:plugin', ['name' => 'LifecycleTestPlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/LifecycleTestPlugin';
        
        $this->assertDirectoryExists($pluginPath);
        $this->assertFileExists($pluginPath . '/config.php');
        $this->assertFileExists($pluginPath . '/routes.php');
        $this->assertFileExists($pluginPath . '/Controllers/LifecycleTestPluginController.php');
        $this->assertFileExists($pluginPath . '/Services/LifecycleTestPluginService.php');
        $this->assertFileExists($pluginPath . '/Services/LifecycleTestPluginServiceInterface.php');
        $this->assertFileExists($pluginPath . '/Views/index.blade.php');
        
        $this->refreshApplication();
        
        $this->assertEquals('LifecycleTestPlugin', config('LifecycleTestPlugin.name'));
        $this->assertTrue(config('LifecycleTestPlugin.enabled'));
        
        $routes = Route::getRoutes();
        $pluginRouteFound = false;
        
        foreach ($routes as $route) {
            if (str_contains($route->uri(), 'lifecycletestplugin')) {
                $pluginRouteFound = true;
                break;
            }
        }
        
        $this->assertTrue($pluginRouteFound);
    }

    public function test_plugin_with_volt_views_complete_lifecycle()
    {
        config(['laravel-plugin-system.enable_volt_support' => true]);
        
        $this->artisan('make:plugin', [
            'name' => 'VoltLifecyclePlugin',
            '--view-type' => 'volt'
        ])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/VoltLifecyclePlugin';
        
        $viewContent = File::get($pluginPath . '/Views/index.blade.php');
        $this->assertStringContains('Livewire\\Volt\\Component', $viewContent);
        
        $routesContent = File::get($pluginPath . '/routes.php');
        $this->assertStringContains('Volt::route', $routesContent);
        
        $this->refreshApplication();
        
        $this->assertEquals('VoltLifecyclePlugin', config('VoltLifecyclePlugin.name'));
    }

    public function test_plugin_with_blade_views_complete_lifecycle()
    {
        $this->artisan('make:plugin', [
            'name' => 'BladeLifecyclePlugin',
            '--view-type' => 'blade'
        ])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/BladeLifecyclePlugin';
        
        $viewContent = File::get($pluginPath . '/Views/index.blade.php');
        $this->assertStringContains('@extends(\'layouts.app\')', $viewContent);
        
        $routesContent = File::get($pluginPath . '/routes.php');
        $this->assertStringContains('Route::get', $routesContent);
        
        $this->refreshApplication();
        
        $this->assertEquals('BladeLifecyclePlugin', config('BladeLifecyclePlugin.name'));
    }

    public function test_multiple_plugins_coexistence()
    {
        $this->artisan('make:plugin', ['name' => 'Plugin1'])->assertExitCode(0);
        $this->artisan('make:plugin', ['name' => 'Plugin2'])->assertExitCode(0);
        $this->artisan('make:plugin', ['name' => 'Plugin3'])->assertExitCode(0);
        
        $this->refreshApplication();
        
        $this->assertEquals('Plugin1', config('Plugin1.name'));
        $this->assertEquals('Plugin2', config('Plugin2.name'));
        $this->assertEquals('Plugin3', config('Plugin3.name'));
        
        $routes = Route::getRoutes();
        $plugin1RouteFound = false;
        $plugin2RouteFound = false;
        $plugin3RouteFound = false;
        
        foreach ($routes as $route) {
            if (str_contains($route->uri(), 'plugin1')) {
                $plugin1RouteFound = true;
            }
            if (str_contains($route->uri(), 'plugin2')) {
                $plugin2RouteFound = true;
            }
            if (str_contains($route->uri(), 'plugin3')) {
                $plugin3RouteFound = true;
            }
        }
        
        $this->assertTrue($plugin1RouteFound);
        $this->assertTrue($plugin2RouteFound);
        $this->assertTrue($plugin3RouteFound);
    }

    public function test_plugin_service_dependency_injection_lifecycle()
    {
        $this->artisan('make:plugin', ['name' => 'DITestPlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/DITestPlugin';
        
        $serviceContent = File::get($pluginPath . '/Services/DITestPluginService.php');
        $this->assertStringContains('class DITestPluginService implements DITestPluginServiceInterface', $serviceContent);
        
        $interfaceContent = File::get($pluginPath . '/Services/DITestPluginServiceInterface.php');
        $this->assertStringContains('interface DITestPluginServiceInterface', $interfaceContent);
        
        require_once $pluginPath . '/Services/DITestPluginServiceInterface.php';
        require_once $pluginPath . '/Services/DITestPluginService.php';
        
        $this->refreshApplication();
        
        $serviceClass = 'Tests\\Fixtures\\Plugins\\DITestPlugin\\Services\\DITestPluginService';
        $interfaceClass = 'Tests\\Fixtures\\Plugins\\DITestPlugin\\Services\\DITestPluginServiceInterface';
        
        $this->assertTrue($this->app->bound($serviceClass));
        $this->assertTrue($this->app->bound($interfaceClass));
        
        $service = $this->app->make($interfaceClass);
        $this->assertInstanceOf($serviceClass, $service);
        
        $result = $service->handle();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertStringContains('DITestPlugin service is working!', $result['message']);
    }

    public function test_plugin_controller_binding_lifecycle()
    {
        $this->artisan('make:plugin', ['name' => 'ControllerTestPlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/ControllerTestPlugin';
        
        $controllerContent = File::get($pluginPath . '/Controllers/ControllerTestPluginController.php');
        $this->assertStringContains('class ControllerTestPluginController extends Controller', $controllerContent);
        
        require_once $pluginPath . '/Controllers/ControllerTestPluginController.php';
        
        $this->refreshApplication();
        
        $controllerClass = 'Tests\\Fixtures\\Plugins\\ControllerTestPlugin\\Controllers\\ControllerTestPluginController';
        $this->assertTrue($this->app->bound($controllerClass));
        
        $controller = $this->app->make($controllerClass);
        $this->assertInstanceOf($controllerClass, $controller);
    }

    public function test_plugin_configuration_customization_lifecycle()
    {
        $this->artisan('make:plugin', ['name' => 'ConfigTestPlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/ConfigTestPlugin';
        
        $customConfig = [
            'name' => 'ConfigTestPlugin',
            'version' => '2.0.0',
            'description' => 'Custom description',
            'enabled' => true,
            'custom_setting' => 'custom_value',
            'features' => ['feature1', 'feature2']
        ];
        
        File::put($pluginPath . '/config.php', "<?php\n\nreturn " . var_export($customConfig, true) . ";\n");
        
        $this->refreshApplication();
        
        $this->assertEquals('ConfigTestPlugin', config('ConfigTestPlugin.name'));
        $this->assertEquals('2.0.0', config('ConfigTestPlugin.version'));
        $this->assertEquals('Custom description', config('ConfigTestPlugin.description'));
        $this->assertEquals('custom_value', config('ConfigTestPlugin.custom_setting'));
        $this->assertEquals(['feature1', 'feature2'], config('ConfigTestPlugin.features'));
    }

    public function test_disabled_plugin_lifecycle()
    {
        $this->artisan('make:plugin', ['name' => 'DisabledPlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/DisabledPlugin';
        
        $disabledConfig = [
            'name' => 'DisabledPlugin',
            'version' => '1.0.0',
            'description' => 'Disabled plugin',
            'enabled' => false,
        ];
        
        File::put($pluginPath . '/config.php', "<?php\n\nreturn " . var_export($disabledConfig, true) . ";\n");
        
        $this->refreshApplication();
        
        $this->assertEquals('DisabledPlugin', config('DisabledPlugin.name'));
        $this->assertFalse(config('DisabledPlugin.enabled'));
    }

    public function test_plugin_with_custom_namespace_lifecycle()
    {
        config(['laravel-plugin-system.plugin_namespace' => 'Custom\\Namespace']);
        
        $this->artisan('make:plugin', ['name' => 'CustomNamespacePlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/CustomNamespacePlugin';
        
        $controllerContent = File::get($pluginPath . '/Controllers/CustomNamespacePluginController.php');
        $this->assertStringContains('namespace Custom\\Namespace\\CustomNamespacePlugin\\Controllers;', $controllerContent);
        
        $serviceContent = File::get($pluginPath . '/Services/CustomNamespacePluginService.php');
        $this->assertStringContains('namespace Custom\\Namespace\\CustomNamespacePlugin\\Services;', $serviceContent);
        
        $this->refreshApplication();
        
        $this->assertEquals('CustomNamespacePlugin', config('CustomNamespacePlugin.name'));
    }

    public function test_plugin_route_prefixing_lifecycle()
    {
        \SoysalTan\LaravelPluginSystem\PluginManager::$usePluginsPrefixInRoutes = true;
        
        $this->artisan('make:plugin', ['name' => 'PrefixTestPlugin'])
            ->assertExitCode(0);
        
        $this->refreshApplication();
        
        $routes = Route::getRoutes();
        $prefixedRouteFound = false;
        
        foreach ($routes as $route) {
            if (str_contains($route->uri(), 'plugins/prefixtestplugin')) {
                $prefixedRouteFound = true;
                break;
            }
        }
        
        $this->assertTrue($prefixedRouteFound);
        
        \SoysalTan\LaravelPluginSystem\PluginManager::$usePluginsPrefixInRoutes = false;
    }

    public function test_plugin_view_namespace_registration_lifecycle()
    {
        $this->artisan('make:plugin', ['name' => 'ViewNamespacePlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/ViewNamespacePlugin';
        $viewsPath = $pluginPath . '/Views';
        
        File::put($viewsPath . '/custom.blade.php', '<div>Custom View Content</div>');
        
        $this->refreshApplication();
        
        $viewPaths = \Illuminate\Support\Facades\View::getFinder()->getPaths();
        $this->assertContains($viewsPath, $viewPaths);
    }

    protected function refreshApplication(): void
    {
        if ($this->app) {
            $this->app->forgetInstance(\SoysalTan\LaravelPluginSystem\PluginManager::class);
            
            $provider = new \SoysalTan\LaravelPluginSystem\LaravelPluginSystemServiceProvider($this->app);
            $provider->register();
            $provider->boot();
        }
    }
}