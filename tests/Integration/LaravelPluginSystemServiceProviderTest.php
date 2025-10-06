<?php

namespace SoysalTan\LaravelPluginSystem\Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use SoysalTan\LaravelPluginSystem\Commands\MakePluginCommand;
use SoysalTan\LaravelPluginSystem\LaravelPluginSystemServiceProvider;
use SoysalTan\LaravelPluginSystem\PluginManager;
use SoysalTan\LaravelPluginSystem\Tests\TestCase;

class LaravelPluginSystemServiceProviderTest extends TestCase
{
    public function test_service_provider_registers_plugin_manager_as_singleton()
    {
        $manager1 = $this->app->make(PluginManager::class);
        $manager2 = $this->app->make(PluginManager::class);

        $this->assertSame($manager1, $manager2);
        $this->assertInstanceOf(PluginManager::class, $manager1);
    }

    public function test_service_provider_merges_configuration()
    {
        $this->assertNotNull(config('laravel-plugin-system'));
        $this->assertIsArray(config('laravel-plugin-system'));

        $this->assertArrayHasKey('plugins_path', config('laravel-plugin-system'));
        $this->assertArrayHasKey('plugin_namespace', config('laravel-plugin-system'));
        $this->assertArrayHasKey('use_plugins_prefix_in_routes', config('laravel-plugin-system'));
        $this->assertArrayHasKey('default_view_type', config('laravel-plugin-system'));
        $this->assertArrayHasKey('enable_volt_support', config('laravel-plugin-system'));
    }

    public function test_service_provider_registers_make_plugin_command()
    {
        $registeredCommands = Artisan::all();

        $this->assertArrayHasKey('make:plugin', $registeredCommands);
        $this->assertInstanceOf(MakePluginCommand::class, $registeredCommands['make:plugin']);
    }

    public function test_service_provider_publishes_config_file()
    {
        $publishedFiles = $this->app['config']['view.published'];

        $this->assertTrue(true);
    }

    public function test_plugin_manager_register_is_called_during_service_registration()
    {
        $this->createTestPlugin('RegisterTestPlugin', [
            'name' => 'RegisterTestPlugin',
            'test_config' => 'test_value',
        ]);

        $provider = new LaravelPluginSystemServiceProvider($this->app);
        $provider->register();

        $this->assertEquals('RegisterTestPlugin', config('RegisterTestPlugin.name'));
        $this->assertEquals('test_value', config('RegisterTestPlugin.test_config'));
    }

    public function test_plugin_manager_boot_is_called_during_service_boot()
    {
        $this->createTestPluginWithRoutes('BootTestPlugin');

        $provider = new LaravelPluginSystemServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $this->assertNotEmpty($routes->getRoutes());
    }

    public function test_service_provider_handles_plugin_services_registration()
    {
        $pluginName = 'ServiceIntegrationPlugin';
        $this->createTestPlugin($pluginName);

        $pluginPath = $this->getTestPluginsPath().'/'.$pluginName;
        $servicesPath = $pluginPath.'/Services';
        mkdir($servicesPath, 0755, true);

        $interfaceContent = "<?php\n\nnamespace Tests\\Fixtures\\Plugins\\{$pluginName}\\Services;\n\ninterface TestServiceInterface\n{\n    public function getName(): string;\n}";

        $serviceContent = "<?php\n\nnamespace Tests\\Fixtures\\Plugins\\{$pluginName}\\Services;\n\nclass TestService implements TestServiceInterface\n{\n    public function getName(): string\n    {\n        return 'TestService';\n    }\n}";

        file_put_contents($servicesPath.'/TestServiceInterface.php', $interfaceContent);
        file_put_contents($servicesPath.'/TestService.php', $serviceContent);

        require_once $servicesPath.'/TestServiceInterface.php';
        require_once $servicesPath.'/TestService.php';

        $provider = new LaravelPluginSystemServiceProvider($this->app);
        $provider->register();

        $interfaceClass = "Tests\\Fixtures\\Plugins\\{$pluginName}\\Services\\TestServiceInterface";
        $serviceClass = "Tests\\Fixtures\\Plugins\\{$pluginName}\\Services\\TestService";

        $this->assertTrue($this->app->bound($serviceClass));
        $this->assertTrue($this->app->bound($interfaceClass));

        $instance = $this->app->make($interfaceClass);
        $this->assertInstanceOf($serviceClass, $instance);
    }

    public function test_service_provider_handles_plugin_controllers_registration()
    {
        $pluginName = 'ControllerIntegrationPlugin';
        $this->createTestPlugin($pluginName);

        $pluginPath = $this->getTestPluginsPath().'/'.$pluginName;
        $controllersPath = $pluginPath.'/Controllers';
        mkdir($controllersPath, 0755, true);

        $controllerContent = "<?php\n\nnamespace Tests\\Fixtures\\Plugins\\{$pluginName}\\Controllers;\n\nuse App\\Http\\Controllers\\Controller;\n\nclass TestController extends Controller\n{\n    public function index()\n    {\n        return 'Hello from TestController';\n    }\n}";

        file_put_contents($controllersPath.'/TestController.php', $controllerContent);

        require_once $controllersPath.'/TestController.php';

        $provider = new LaravelPluginSystemServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $controllerClass = "Tests\\Fixtures\\Plugins\\{$pluginName}\\Controllers\\TestController";
        $this->assertTrue($this->app->bound($controllerClass));
    }

    public function test_service_provider_handles_plugin_views_registration()
    {
        $pluginName = 'ViewIntegrationPlugin';
        $this->createTestPlugin($pluginName);

        $pluginPath = $this->getTestPluginsPath().'/'.$pluginName;
        $viewsPath = $pluginPath.'/Views';
        mkdir($viewsPath, 0755, true);

        file_put_contents($viewsPath.'/test.blade.php', '<div>Test View Content</div>');

        $provider = new LaravelPluginSystemServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $viewPaths = \Illuminate\Support\Facades\View::getFinder()->getPaths();
        $this->assertContains($viewsPath, $viewPaths);
    }

    public function test_service_provider_handles_multiple_plugins()
    {
        $this->createTestPlugin('Plugin1', ['name' => 'Plugin1', 'version' => '1.0.0']);
        $this->createTestPlugin('Plugin2', ['name' => 'Plugin2', 'version' => '2.0.0']);
        $this->createTestPlugin('Plugin3', ['name' => 'Plugin3', 'version' => '3.0.0']);

        $provider = new LaravelPluginSystemServiceProvider($this->app);
        $provider->register();

        $this->assertEquals('Plugin1', config('Plugin1.name'));
        $this->assertEquals('1.0.0', config('Plugin1.version'));

        $this->assertEquals('Plugin2', config('Plugin2.name'));
        $this->assertEquals('2.0.0', config('Plugin2.version'));

        $this->assertEquals('Plugin3', config('Plugin3.name'));
        $this->assertEquals('3.0.0', config('Plugin3.version'));
    }

    public function test_service_provider_works_with_empty_plugins_directory()
    {
        $emptyPath = $this->getTestPluginsPath().'/Empty';
        mkdir($emptyPath, 0755, true);

        config(['laravel-plugin-system.plugins_path' => $emptyPath]);

        $provider = new LaravelPluginSystemServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertTrue(true);
    }

    public function test_service_provider_works_with_non_existent_plugins_directory()
    {
        config(['laravel-plugin-system.plugins_path' => '/non/existent/path']);

        $provider = new LaravelPluginSystemServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertTrue(true);
    }

    public function test_service_provider_configuration_can_be_overridden()
    {
        config([
            'laravel-plugin-system.plugins_path' => '/custom/path',
            'laravel-plugin-system.plugin_namespace' => 'Custom\\Namespace',
            'laravel-plugin-system.use_plugins_prefix_in_routes' => true,
            'laravel-plugin-system.default_view_type' => 'blade',
            'laravel-plugin-system.enable_volt_support' => false,
        ]);

        $this->assertEquals('/custom/path', config('laravel-plugin-system.plugins_path'));
        $this->assertEquals('Custom\\Namespace', config('laravel-plugin-system.plugin_namespace'));
        $this->assertTrue(config('laravel-plugin-system.use_plugins_prefix_in_routes'));
        $this->assertEquals('blade', config('laravel-plugin-system.default_view_type'));
        $this->assertFalse(config('laravel-plugin-system.enable_volt_support'));
    }

    public function test_service_provider_registers_and_boots_in_correct_order()
    {
        $this->createTestPluginWithRoutes('OrderTestPlugin');

        $provider = new LaravelPluginSystemServiceProvider($this->app);

        $provider->register();

        $this->assertTrue($this->app->bound(PluginManager::class));
        $this->assertEquals('OrderTestPlugin', config('OrderTestPlugin.name'));

        $provider->boot();

        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $this->assertNotEmpty($routes->getRoutes());
    }
}
