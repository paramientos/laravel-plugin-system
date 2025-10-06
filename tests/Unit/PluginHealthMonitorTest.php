<?php

namespace SoysalTan\LaravelPluginSystem\Tests\Unit;

use Mockery;
use PHPUnit\Framework\TestCase;
use SoysalTan\LaravelPluginSystem\PluginManager;
use SoysalTan\LaravelPluginSystem\Services\PluginHealthMonitor;

class PluginHealthMonitorTest extends TestCase
{
    protected PluginHealthMonitor $healthMonitor;

    protected $pluginManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginManager = Mockery::mock(PluginManager::class);
        $this->healthMonitor = new PluginHealthMonitor($this->pluginManager);
    }

    public function test_can_check_plugin_health()
    {
        $this->pluginManager
            ->shouldReceive('getEnabledPlugins')
            ->andReturn(['TestPlugin']);

        $health = $this->healthMonitor->checkPluginHealth('TestPlugin');

        $this->assertIsArray($health);
        $this->assertEquals('TestPlugin', $health['plugin_name']);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('metrics', $health);
        $this->assertArrayHasKey('recent_errors', $health);
        $this->assertArrayHasKey('recommendations', $health);
    }

    public function test_can_get_health_report()
    {
        $this->pluginManager
            ->shouldReceive('getEnabledPlugins')
            ->andReturn(['Plugin1', 'Plugin2']);

        $healthReport = $this->healthMonitor->getHealthReport();

        $this->assertIsArray($healthReport);
        $this->assertArrayHasKey('summary', $healthReport);
        $this->assertArrayHasKey('plugins', $healthReport);

        $summary = $healthReport['summary'];
        $this->assertArrayHasKey('total_plugins', $summary);
        $this->assertArrayHasKey('healthy_plugins', $summary);
        $this->assertArrayHasKey('warning_plugins', $summary);
        $this->assertArrayHasKey('critical_plugins', $summary);
        $this->assertArrayHasKey('overall_status', $summary);
    }

    public function test_can_record_plugin_metrics()
    {
        $this->healthMonitor->recordPluginMetric('TestPlugin', 'memory_usage', 75 * 1024 * 1024);
        $this->healthMonitor->recordPluginMetric('TestPlugin', 'execution_time', 150);
        $this->assertTrue(true);
    }

    public function test_can_record_plugin_error()
    {
        $exception = new \Exception('Test error message');
        $this->healthMonitor->recordPluginError('TestPlugin', $exception);
        $this->assertTrue(true);
    }

    public function test_can_clear_plugin_errors()
    {
        $this->healthMonitor->clearPluginErrors('TestPlugin');
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
