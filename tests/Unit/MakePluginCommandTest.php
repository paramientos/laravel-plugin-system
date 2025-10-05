<?php

namespace SoysalTan\LaravelPluginSystem\Tests\Unit;

use Illuminate\Support\Facades\File;
use SoysalTan\LaravelPluginSystem\Commands\MakePluginCommand;
use SoysalTan\LaravelPluginSystem\Tests\TestCase;

class MakePluginCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupTestPlugins();
    }

    public function test_creates_plugin_with_default_settings()
    {
        $this->artisan('make:plugin', ['name' => 'TestPlugin'])
            ->expectsOutputToContain('Creating plugin: TestPlugin')
            ->expectsOutputToContain('Plugin \'TestPlugin\' created successfully!')
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/TestPlugin';

        $this->assertDirectoryExists($pluginPath);
        $this->assertDirectoryExists($pluginPath . '/Controllers');
        $this->assertDirectoryExists($pluginPath . '/Services');
        $this->assertDirectoryExists($pluginPath . '/Views');

        $this->assertFileExists($pluginPath . '/config.php');
        $this->assertFileExists($pluginPath . '/routes.php');
        $this->assertFileExists($pluginPath . '/Controllers/TestPluginController.php');
        $this->assertFileExists($pluginPath . '/Services/TestPluginService.php');
        $this->assertFileExists($pluginPath . '/Services/TestPluginServiceInterface.php');
        $this->assertFileExists($pluginPath . '/Views/index.blade.php');
    }

    public function test_creates_plugin_with_volt_view_type()
    {
        config(['laravel-plugin-system.enable_volt_support' => true]);

        $this->artisan('make:plugin', [
            'name' => 'VoltPlugin',
            '--view-type' => 'volt'
        ])
            ->expectsOutputToContain('Creating plugin: VoltPlugin')
            ->expectsOutputToContain('View type: volt')
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/VoltPlugin';
        $viewContent = File::get($pluginPath . '/Views/index.blade.php');

        $this->assertStringContainsString('Livewire\\Volt\\Component', $viewContent);
        $this->assertStringContainsString('Welcome to VoltPlugin Plugin!', $viewContent);
    }

    public function test_creates_plugin_with_blade_view_type()
    {
        $this->artisan('make:plugin', [
            'name' => 'BladePlugin',
            '--view-type' => 'blade'
        ])
            ->expectsOutputToContain('Creating plugin: BladePlugin')
            ->expectsOutputToContain('View type: blade')
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/BladePlugin';
        $viewContent = File::get($pluginPath . '/Views/index.blade.php');

        $this->assertStringContainsString('@extends(\'layouts.app\')', $viewContent);
        $this->assertStringContainsString('Welcome to BladePlugin Plugin!', $viewContent);
    }

    public function test_auto_view_type_defaults_to_volt_when_available()
    {
        config([
            'laravel-plugin-system.default_view_type' => 'volt',
            'laravel-plugin-system.enable_volt_support' => true
        ]);

        $this->artisan('make:plugin', [
            'name' => 'AutoPlugin',
            '--view-type' => 'auto'
        ])
            ->expectsOutputToContain('View type: volt')
            ->assertExitCode(0);
    }

    public function test_auto_view_type_falls_back_to_blade_when_volt_unavailable()
    {
        config([
            'laravel-plugin-system.default_view_type' => 'volt',
            'laravel-plugin-system.enable_volt_support' => false
        ]);

        $this->artisan('make:plugin', [
            'name' => 'AutoBladePlugin',
            '--view-type' => 'auto'
        ])
            ->expectsOutputToContain('View type: blade')
            ->assertExitCode(0);
    }

    public function test_fails_when_plugin_already_exists()
    {
        $this->createTestPlugin('ExistingPlugin');

        $this->artisan('make:plugin', ['name' => 'ExistingPlugin'])
            ->expectsOutputToContain('Plugin \'ExistingPlugin\' already exists!')
            ->assertExitCode(1);
    }

    public function test_fails_with_invalid_view_type()
    {
        $this->artisan('make:plugin', [
            'name' => 'InvalidPlugin',
            '--view-type' => 'invalid'
        ])
            ->expectsOutputToContain('Invalid view type \'invalid\'. Available options: volt, blade, auto')
            ->assertExitCode(1);
    }

    public function test_config_file_contains_correct_plugin_information()
    {
        $this->artisan('make:plugin', ['name' => 'ConfigTestPlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/ConfigTestPlugin';
        $config = require $pluginPath . '/config.php';

        $this->assertEquals('ConfigTestPlugin', $config['name']);
        $this->assertEquals('1.0.0', $config['version']);
        $this->assertEquals('ConfigTestPlugin plugin', $config['description']);
        $this->assertTrue($config['enabled']);
    }

    public function test_controller_file_has_correct_namespace_and_class()
    {
        $this->artisan('make:plugin', ['name' => 'ControllerTestPlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/ControllerTestPlugin';
        $controllerContent = File::get($pluginPath . '/Controllers/ControllerTestPluginController.php');

        $this->assertStringContainsString('namespace Tests\\Fixtures\\Plugins\\ControllerTestPlugin\\Controllers;', $controllerContent);
        $this->assertStringContainsString('class ControllerTestPluginController extends Controller', $controllerContent);
        $this->assertStringContainsString('public function index()', $controllerContent);
    }

    public function test_service_files_have_correct_interface_and_implementation()
    {
        $this->artisan('make:plugin', ['name' => 'ServiceTestPlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/ServiceTestPlugin';

        $interfaceContent = File::get($pluginPath . '/Services/ServiceTestPluginServiceInterface.php');
        $serviceContent = File::get($pluginPath . '/Services/ServiceTestPluginService.php');

        $this->assertStringContainsString('interface ServiceTestPluginServiceInterface', $interfaceContent);
        $this->assertStringContainsString('public function handle(): array;', $interfaceContent);

        $this->assertStringContainsString('class ServiceTestPluginService implements ServiceTestPluginServiceInterface', $serviceContent);
        $this->assertStringContainsString('public function handle(): array', $serviceContent);
        $this->assertStringContainsString('ServiceTestPlugin service is working!', $serviceContent);
    }

    public function test_routes_file_for_volt_view_type()
    {
        config(['laravel-plugin-system.enable_volt_support' => true]);

        $this->artisan('make:plugin', [
            'name' => 'VoltRoutesPlugin',
            '--view-type' => 'volt'
        ])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/VoltRoutesPlugin';
        $routesContent = File::get($pluginPath . '/routes.php');

        $this->assertStringContainsString('use Livewire\\Volt\\Volt;', $routesContent);
        $this->assertStringContainsString('Volt::route(\'/\', \'index\');', $routesContent);
    }

    public function test_routes_file_for_blade_view_type()
    {
        $this->artisan('make:plugin', [
            'name' => 'BladeRoutesPlugin',
            '--view-type' => 'blade'
        ])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/BladeRoutesPlugin';
        $routesContent = File::get($pluginPath . '/routes.php');

        $this->assertStringContainsString('use Illuminate\\Support\\Facades\\Route;', $routesContent);
        $this->assertStringContainsString('Route::get(\'/\', function () {', $routesContent);
        $this->assertStringContainsString('return view(\'plugins.bladeroutesplugin::index\');', $routesContent);
    }

    public function test_plugin_name_is_converted_to_studly_case()
    {
        $this->artisan('make:plugin', ['name' => 'my-awesome-plugin'])
            ->expectsOutputToContain('Creating plugin: MyAwesomePlugin')
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/MyAwesomePlugin';
        $this->assertDirectoryExists($pluginPath);
    }

    public function test_uses_custom_plugins_path_from_config()
    {
        $customPath = $this->getTestPluginsPath() . '/Custom';
        config(['laravel-plugin-system.plugins_path' => $customPath]);

        $this->artisan('make:plugin', ['name' => 'CustomPathPlugin'])
            ->assertExitCode(0);

        $this->assertDirectoryExists($customPath . '/CustomPathPlugin');
    }

    public function test_uses_custom_plugin_namespace_from_config()
    {
        config(['laravel-plugin-system.plugin_namespace' => 'Custom\\Namespace']);

        $this->artisan('make:plugin', ['name' => 'CustomNamespacePlugin'])
            ->assertExitCode(0);

        $pluginPath = $this->getTestPluginsPath() . '/CustomNamespacePlugin';
        $controllerContent = File::get($pluginPath . '/Controllers/CustomNamespacePluginController.php');

        $this->assertStringContainsString('namespace Custom\\Namespace\\CustomNamespacePlugin\\Controllers;', $controllerContent);
    }

    public function test_command_shows_success_information()
    {
        $this->artisan('make:plugin', ['name' => 'InfoPlugin'])
            ->expectsOutputToContain('Plugin \'InfoPlugin\' created successfully!')
            ->expectsOutputToContain('Plugin location:')
            ->expectsOutputToContain('Plugin is enabled by default')
            ->expectsOutputToContain('Plugin routes are registered')
            ->expectsOutputToContain('You can access to index view page via url')
            ->assertExitCode(0);
    }

    public function test_creates_all_required_directories()
    {
        $this->artisan('make:plugin', ['name' => 'DirectoryTestPlugin'])
            ->expectsOutputToContain('Created directory: DirectoryTestPlugin')
            ->expectsOutputToContain('Created directory: Controllers')
            ->expectsOutputToContain('Created directory: Services')
            ->expectsOutputToContain('Created directory: Views')
            ->assertExitCode(0);
    }

    public function test_creates_all_required_files()
    {
        $this->artisan('make:plugin', ['name' => 'FileTestPlugin'])
            ->expectsOutputToContain('Created: config.php')
            ->expectsOutputToContain('Created: routes.php')
            ->expectsOutputToContain('Created: FileTestPluginController.php')
            ->expectsOutputToContain('Created: FileTestPluginServiceInterface.php')
            ->expectsOutputToContain('Created: FileTestPluginService.php')
            ->expectsOutputToContain('Created: index.blade.php')
            ->assertExitCode(0);
    }
}
