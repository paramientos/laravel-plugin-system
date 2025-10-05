<?php

namespace SoysalTan\LaravelPluginSystem\Tests\Unit;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use SoysalTan\LaravelPluginSystem\PluginManager;
use SoysalTan\LaravelPluginSystem\Tests\TestCase;

class PluginManagerTest extends TestCase
{
    protected PluginManager $pluginManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginManager = new PluginManager($this->app);
    }

    public function test_constructor_sets_plugins_path_from_config()
    {
        $customPath = '/custom/plugins/path';
        config(['laravel-plugin-system.plugins_path' => $customPath]);
        
        $manager = new PluginManager($this->app);
        
        $reflection = new \ReflectionClass($manager);
        $property = $reflection->getProperty('pluginsPath');
        $property->setAccessible(true);
        
        $this->assertEquals($customPath, $property->getValue($manager));
    }

    public function test_register_calls_config_and_services_registration()
    {
        $this->createTestPlugin('TestPlugin');
        
        $this->pluginManager->register();
        
        $this->assertTrue(config('TestPlugin.enabled'));
        $this->assertEquals('TestPlugin', config('TestPlugin.name'));
    }

    public function test_boot_calls_views_routes_and_controllers_registration()
    {
        $this->createTestPluginWithRoutes('TestPlugin');
        
        $this->pluginManager->boot();
        
        $routes = Route::getRoutes();
        $this->assertNotEmpty($routes->getRoutes());
    }

    public function test_register_plugin_commands_returns_make_plugin_command()
    {
        $commands = $this->pluginManager->registerPluginCommands();
        
        $this->assertContains(
            'SoysalTan\LaravelPluginSystem\Commands\MakePluginCommand',
            $commands
        );
    }

    public function test_register_plugin_configs_loads_plugin_configurations()
    {
        $this->createTestPlugin('TestPlugin', [
            'name' => 'TestPlugin',
            'version' => '2.0.0',
            'custom_setting' => 'test_value'
        ]);
        
        $this->pluginManager->register();
        
        $this->assertEquals('TestPlugin', config('TestPlugin.name'));
        $this->assertEquals('2.0.0', config('TestPlugin.version'));
        $this->assertEquals('test_value', config('TestPlugin.custom_setting'));
    }

    public function test_register_plugin_configs_handles_missing_plugins_directory()
    {
        config(['laravel-plugin-system.plugins_path' => '/non/existent/path']);
        $manager = new PluginManager($this->app);
        
        $manager->register();
        
        $this->assertTrue(true);
    }

    public function test_register_plugin_views_adds_view_locations_and_namespaces()
    {
        $pluginName = 'TestPlugin';
        $this->createTestPlugin($pluginName);
        
        $pluginPath = $this->getTestPluginsPath() . '/' . $pluginName;
        $viewsPath = $pluginPath . '/Views';
        mkdir($viewsPath, 0755, true);
        
        file_put_contents($viewsPath . '/test.blade.php', '<div>Test View</div>');
        
        $this->pluginManager->boot();
        
        $viewPaths = View::getFinder()->getPaths();
        $this->assertContains($viewsPath, $viewPaths);
    }

    public function test_register_plugin_routes_with_default_prefix()
    {
        $this->createTestPluginWithRoutes('TestPlugin');
        
        PluginManager::$usePluginsPrefixInRoutes = false;
        
        $this->pluginManager->boot();
        
        $routes = Route::getRoutes();
        $routeFound = false;
        
        foreach ($routes as $route) {
            if (str_contains($route->uri(), 'testplugin')) {
                $routeFound = true;
                break;
            }
        }
        
        $this->assertTrue($routeFound);
    }

    public function test_register_plugin_routes_with_plugins_prefix()
    {
        $this->createTestPluginWithRoutes('TestPlugin');
        
        PluginManager::$usePluginsPrefixInRoutes = true;
        
        $this->pluginManager->boot();
        
        $routes = Route::getRoutes();
        $routeFound = false;
        
        foreach ($routes as $route) {
            if (str_contains($route->uri(), 'plugins/testplugin')) {
                $routeFound = true;
                break;
            }
        }
        
        $this->assertTrue($routeFound);
        
        PluginManager::$usePluginsPrefixInRoutes = false;
    }

    public function test_register_plugin_controllers_binds_controllers_to_container()
    {
        $pluginName = 'TestPlugin';
        $this->createTestPlugin($pluginName);
        
        $pluginPath = $this->getTestPluginsPath() . '/' . $pluginName;
        $controllersPath = $pluginPath . '/Controllers';
        mkdir($controllersPath, 0755, true);
        
        $controllerContent = "<?php\n\nnamespace Tests\\Fixtures\\Plugins\\{$pluginName}\\Controllers;\n\nclass TestController\n{\n    public function index()\n    {\n        return 'Hello from TestController';\n    }\n}";
        
        file_put_contents($controllersPath . '/TestController.php', $controllerContent);
        
        require_once $controllersPath . '/TestController.php';
        
        $this->pluginManager->boot();
        
        $controllerClass = "Tests\\Fixtures\\Plugins\\{$pluginName}\\Controllers\\TestController";
        $this->assertTrue($this->app->bound($controllerClass));
    }

    public function test_register_plugin_services_binds_services_as_singletons()
    {
        $pluginName = 'TestPlugin';
        $this->createTestPlugin($pluginName);
        
        $pluginPath = $this->getTestPluginsPath() . '/' . $pluginName;
        $servicesPath = $pluginPath . '/Services';
        mkdir($servicesPath, 0755, true);
        
        $serviceContent = "<?php\n\nnamespace Tests\\Fixtures\\Plugins\\{$pluginName}\\Services;\n\nclass TestService\n{\n    public function getName(): string\n    {\n        return 'TestService';\n    }\n}";
        
        file_put_contents($servicesPath . '/TestService.php', $serviceContent);
        
        require_once $servicesPath . '/TestService.php';
        
        $this->pluginManager->register();
        
        $serviceClass = "Tests\\Fixtures\\Plugins\\{$pluginName}\\Services\\TestService";
        $this->assertTrue($this->app->bound($serviceClass));
        
        $instance1 = $this->app->make($serviceClass);
        $instance2 = $this->app->make($serviceClass);
        
        $this->assertSame($instance1, $instance2);
    }

    public function test_register_plugin_services_binds_interface_to_implementation()
    {
        $pluginName = 'TestPlugin';
        $this->createTestPlugin($pluginName);
        
        $pluginPath = $this->getTestPluginsPath() . '/' . $pluginName;
        $servicesPath = $pluginPath . '/Services';
        mkdir($servicesPath, 0755, true);
        
        $interfaceContent = "<?php\n\nnamespace Tests\\Fixtures\\Plugins\\{$pluginName}\\Services;\n\ninterface TestServiceInterface\n{\n    public function getName(): string;\n}";
        
        $serviceContent = "<?php\n\nnamespace Tests\\Fixtures\\Plugins\\{$pluginName}\\Services;\n\nclass TestService implements TestServiceInterface\n{\n    public function getName(): string\n    {\n        return 'TestService';\n    }\n}";
        
        file_put_contents($servicesPath . '/TestServiceInterface.php', $interfaceContent);
        file_put_contents($servicesPath . '/TestService.php', $serviceContent);
        
        require_once $servicesPath . '/TestServiceInterface.php';
        require_once $servicesPath . '/TestService.php';
        
        $this->pluginManager->register();
        
        $interfaceClass = "Tests\\Fixtures\\Plugins\\{$pluginName}\\Services\\TestServiceInterface";
        $serviceClass = "Tests\\Fixtures\\Plugins\\{$pluginName}\\Services\\TestService";
        
        $this->assertTrue($this->app->bound($interfaceClass));
        
        $instance = $this->app->make($interfaceClass);
        $this->assertInstanceOf($serviceClass, $instance);
    }

    public function test_get_plugin_namespace_returns_correct_namespace()
    {
        $pluginName = 'TestPlugin';
        
        $reflection = new \ReflectionClass($this->pluginManager);
        $method = $reflection->getMethod('getPluginNamespace');
        $method->setAccessible(true);
        
        $namespace = $method->invoke($this->pluginManager, $pluginName);
        
        $this->assertEquals('Tests\\Fixtures\\Plugins\\TestPlugin', $namespace);
    }

    public function test_get_plugin_namespace_uses_custom_config()
    {
        config(['laravel-plugin-system.plugin_namespace' => 'Custom\\Namespace']);
        
        $manager = new PluginManager($this->app);
        $pluginName = 'TestPlugin';
        
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('getPluginNamespace');
        $method->setAccessible(true);
        
        $namespace = $method->invoke($manager, $pluginName);
        
        $this->assertEquals('Custom\\Namespace\\TestPlugin', $namespace);
    }

    public function test_handles_non_php_files_in_services_directory()
    {
        $pluginName = 'TestPlugin';
        $this->createTestPlugin($pluginName);
        
        $pluginPath = $this->getTestPluginsPath() . '/' . $pluginName;
        $servicesPath = $pluginPath . '/Services';
        mkdir($servicesPath, 0755, true);
        
        file_put_contents($servicesPath . '/readme.txt', 'This is not a PHP file');
        
        $this->pluginManager->register();
        
        $this->assertTrue(true);
    }

    public function test_handles_non_php_files_in_controllers_directory()
    {
        $pluginName = 'TestPlugin';
        $this->createTestPlugin($pluginName);
        
        $pluginPath = $this->getTestPluginsPath() . '/' . $pluginName;
        $controllersPath = $pluginPath . '/Controllers';
        mkdir($controllersPath, 0755, true);
        
        file_put_contents($controllersPath . '/readme.txt', 'This is not a PHP file');
        
        $this->pluginManager->boot();
        
        $this->assertTrue(true);
    }
}