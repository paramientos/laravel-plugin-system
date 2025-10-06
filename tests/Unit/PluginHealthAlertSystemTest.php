<?php

namespace SoysalTan\LaravelPluginSystem\Tests\Unit;

use Mockery;
use PHPUnit\Framework\TestCase;
use SoysalTan\LaravelPluginSystem\Services\PluginHealthAlertSystem;
use SoysalTan\LaravelPluginSystem\Services\PluginHealthMonitor;

class PluginHealthAlertSystemTest extends TestCase
{
    protected PluginHealthAlertSystem $alertSystem;

    protected $healthMonitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->healthMonitor = Mockery::mock(PluginHealthMonitor::class);
        $this->alertSystem = new PluginHealthAlertSystem($this->healthMonitor);
    }

    public function test_can_process_plugin_alerts()
    {
        $pluginHealth = [
            'plugin_name' => 'TestPlugin',
            'status' => 'critical',
            'uptime' => 85.0,
            'metrics' => [
                'memory_usage' => 200 * 1024 * 1024,
                'error_count' => 10,
                'response_time' => 5000,
            ],
            'recent_errors' => [],
            'recommendations' => [],
        ];

        $this->alertSystem->processPluginAlerts($pluginHealth);
        $this->assertTrue(true);
    }

    public function test_can_process_system_alerts()
    {
        $summary = [
            'total_plugins' => 2,
            'healthy_plugins' => 1,
            'critical_plugins' => 1,
            'overall_status' => 'critical',
        ];

        $this->alertSystem->processSystemAlerts($summary);
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
