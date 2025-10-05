<?php

namespace SoysalTan\LaravelPluginSystem\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SoysalTan\LaravelPluginSystem\LaravelPluginSystemServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelPluginSystemServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('laravel-plugin-system.plugins_path', $this->getTestPluginsPath());
        config()->set('laravel-plugin-system.plugin_namespace', 'Tests\\Fixtures\\Plugins');
    }

    protected function getTestPluginsPath(): string
    {
        return __DIR__ . '/Fixtures/Plugins';
    }

    protected function createTestPlugin(string $name, array $config = []): void
    {
        $pluginPath = $this->getTestPluginsPath() . '/' . $name;

        if (!is_dir($pluginPath)) {
            mkdir($pluginPath, 0755, true);
        }

        $defaultConfig = [
            'name' => $name,
            'version' => '1.0.0',
            'description' => "Test plugin: {$name}",
            'enabled' => true,
        ];

        $config = array_merge($defaultConfig, $config);

        file_put_contents(
            $pluginPath . '/config.php',
            "<?php\n\nreturn " . var_export($config, true) . ";\n"
        );
    }

    protected function createTestPluginWithRoutes(string $name, string $routeContent = ''): void
    {
        $this->createTestPlugin($name);

        $pluginPath = $this->getTestPluginsPath() . '/' . $name;

        if (empty($routeContent)) {
            $routeContent = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/{$name}', function () {\n    return 'Hello from {$name}';\n});";
        }

        file_put_contents($pluginPath . '/routes.php', $routeContent);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestPlugins();
        parent::tearDown();
    }

    protected function cleanupTestPlugins(): void
    {
        $pluginsPath = $this->getTestPluginsPath();

        if (is_dir($pluginsPath)) {
            $this->deleteDirectory($pluginsPath);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
