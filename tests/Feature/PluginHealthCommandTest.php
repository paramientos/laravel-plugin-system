<?php

namespace SoysalTan\LaravelPluginSystem\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PluginHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock health data
        Cache::put('plugin_health_TestPlugin_metrics', [
            'memory_usage' => 50 * 1024 * 1024,
            'execution_time' => 100,
            'request_count' => 10,
            'error_count' => 0,
            'response_time' => 200,
            'database_queries' => 5,
            'cache_hits' => 8,
            'cache_misses' => 2,
        ], 3600);

        Cache::put('plugin_health_TestPlugin_errors', [], 86400);
        Cache::put('plugin_health_TestPlugin_uptime', 98.5, 86400);
    }

    public function test_plugin_health_command_shows_all_plugins()
    {
        $this->artisan('plugin:health')
            ->expectsOutput('Plugin Health Monitor')
            ->assertExitCode(0);
    }

    public function test_plugin_health_command_shows_specific_plugin()
    {
        $this->artisan('plugin:health', ['plugin' => 'TestPlugin'])
            ->expectsOutput('Plugin Health Monitor')
            ->assertExitCode(0);
    }

    public function test_plugin_health_command_with_json_output()
    {
        $this->artisan('plugin:health', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_plugin_health_command_with_detailed_output()
    {
        $this->artisan('plugin:health', ['--detailed' => true])
            ->expectsOutput('Plugin Health Monitor')
            ->assertExitCode(0);
    }

    public function test_plugin_health_command_shows_errors()
    {
        // Add some errors to cache
        Cache::put('plugin_health_TestPlugin_errors', [
            [
                'message' => 'Test error message',
                'timestamp' => now()->toISOString(),
                'file' => 'TestPlugin.php',
                'line' => 42,
            ],
        ], 86400);

        $this->artisan('plugin:health', ['--errors' => true])
            ->expectsOutput('Plugin Health Monitor')
            ->assertExitCode(0);
    }

    public function test_plugin_health_command_clears_errors()
    {
        // Add some errors first
        Cache::put('plugin_health_TestPlugin_errors', [
            ['message' => 'Test error', 'timestamp' => now()->toISOString()],
        ], 86400);

        $this->artisan('plugin:health', ['--clear-errors' => true])
            ->expectsOutput('Errors cleared for all plugins.')
            ->assertExitCode(0);

        // Verify errors are cleared
        $this->assertEmpty(Cache::get('plugin_health_TestPlugin_errors', []));
    }

    public function test_plugin_health_command_clears_specific_plugin_errors()
    {
        // Add some errors first
        Cache::put('plugin_health_TestPlugin_errors', [
            ['message' => 'Test error', 'timestamp' => now()->toISOString()],
        ], 86400);

        $this->artisan('plugin:health', [
            'plugin' => 'TestPlugin',
            '--clear-errors' => true,
        ])
            ->expectsOutput('Errors cleared for plugin: TestPlugin')
            ->assertExitCode(0);

        // Verify errors are cleared
        $this->assertEmpty(Cache::get('plugin_health_TestPlugin_errors', []));
    }

    public function test_plugin_health_command_handles_nonexistent_plugin()
    {
        $this->artisan('plugin:health', ['plugin' => 'NonExistentPlugin'])
            ->expectsOutput('Plugin NonExistentPlugin not found or not enabled.')
            ->assertExitCode(1);
    }

    public function test_plugin_health_command_with_multiple_options()
    {
        $this->artisan('plugin:health', [
            '--detailed' => true,
            '--errors' => true,
            '--json' => false,
        ])
            ->expectsOutput('Plugin Health Monitor')
            ->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
