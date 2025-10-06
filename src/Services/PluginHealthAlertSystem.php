<?php

namespace SoysalTan\LaravelPluginSystem\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Cache;
use SoysalTan\LaravelPluginSystem\Services\PluginHealthMonitor;

class PluginHealthAlertSystem
{
    protected PluginHealthMonitor $healthMonitor;
    protected array $alertChannels;
    protected array $alertThresholds;
    protected string $cachePrefix = 'plugin_alert_';

    public function __construct(PluginHealthMonitor $healthMonitor)
    {
        $this->healthMonitor = $healthMonitor;

        $this->alertChannels = config('laravel-plugin-system.health_monitoring.alert_channels', ['log']);

        $this->alertThresholds = config('laravel-plugin-system.health_monitoring.alert_thresholds', [
            'critical_error_count' => 5,
            'warning_error_count' => 10,
            'memory_threshold' => 100 * 1024 * 1024, // 100MB
            'response_time_threshold' => 5000, // 5 seconds
            'uptime_threshold' => 95, // 95%
        ]);
    }

    public function checkAndSendAlerts(): void
    {
        $healthReport = $this->healthMonitor->getHealthReport();

        foreach ($healthReport['plugins'] as $pluginHealth) {
            $this->processPluginAlerts($pluginHealth);
        }

        $this->processSystemAlerts($healthReport['summary']);
    }

    public function processPluginAlerts(array $pluginHealth): void
    {
        $pluginName = $pluginHealth['plugin_name'];
        $alerts = $this->generatePluginAlerts($pluginHealth);

        foreach ($alerts as $alert) {
            if ($this->shouldSendAlert($pluginName, $alert['type'])) {
                $this->sendAlert($alert);
                $this->recordAlertSent($pluginName, $alert['type']);
            }
        }
    }

    public function processSystemAlerts(array $summary): void
    {
        $alerts = $this->generateSystemAlerts($summary);

        foreach ($alerts as $alert) {
            if ($this->shouldSendAlert('system', $alert['type'])) {
                $this->sendAlert($alert);
                $this->recordAlertSent('system', $alert['type']);
            }
        }
    }

    protected function generatePluginAlerts(array $pluginHealth): array
    {
        $alerts = [];
        $pluginName = $pluginHealth['plugin_name'];
        $metrics = $pluginHealth['metrics'];
        $errors = $pluginHealth['recent_errors'];

        // Critical status alert
        if ($pluginHealth['status'] === 'critical') {
            $alerts[] = [
                'type' => 'critical_status',
                'severity' => 'critical',
                'plugin' => $pluginName,
                'title' => "Plugin {$pluginName} is in Critical State",
                'message' => "Plugin {$pluginName} has entered a critical state. Immediate attention required.",
                'details' => [
                    'status' => $pluginHealth['status'],
                    'uptime' => $pluginHealth['uptime'],
                    'recommendations' => $pluginHealth['recommendations'],
                ],
            ];
        }

        // High error count alert
        $errorCount = count($errors);
        if ($errorCount >= $this->alertThresholds['critical_error_count']) {
            $alerts[] = [
                'type' => 'high_error_count',
                'severity' => 'critical',
                'plugin' => $pluginName,
                'title' => "High Error Count in Plugin {$pluginName}",
                'message' => "Plugin {$pluginName} has {$errorCount} recent errors.",
                'details' => [
                    'error_count' => $errorCount,
                    'recent_errors' => array_slice($errors, 0, 3),
                ],
            ];
        }

        // Memory usage alert
        if ($metrics['memory_usage'] > $this->alertThresholds['memory_threshold']) {
            $alerts[] = [
                'type' => 'high_memory_usage',
                'severity' => 'warning',
                'plugin' => $pluginName,
                'title' => "High Memory Usage in Plugin {$pluginName}",
                'message' => "Plugin {$pluginName} is using " . $this->formatBytes($metrics['memory_usage']) . " of memory.",
                'details' => [
                    'memory_usage' => $metrics['memory_usage'],
                    'threshold' => $this->alertThresholds['memory_threshold'],
                ],
            ];
        }

        // Response time alert
        if ($metrics['response_time'] > $this->alertThresholds['response_time_threshold']) {
            $alerts[] = [
                'type' => 'slow_response_time',
                'severity' => 'warning',
                'plugin' => $pluginName,
                'title' => "Slow Response Time in Plugin {$pluginName}",
                'message' => "Plugin {$pluginName} has an average response time of {$metrics['response_time']}ms.",
                'details' => [
                    'response_time' => $metrics['response_time'],
                    'threshold' => $this->alertThresholds['response_time_threshold'],
                ],
            ];
        }

        // Low uptime alert
        if ($pluginHealth['uptime'] < $this->alertThresholds['uptime_threshold']) {
            $alerts[] = [
                'type' => 'low_uptime',
                'severity' => 'warning',
                'plugin' => $pluginName,
                'title' => "Low Uptime for Plugin {$pluginName}",
                'message' => "Plugin {$pluginName} has {$pluginHealth['uptime']}% uptime.",
                'details' => [
                    'uptime' => $pluginHealth['uptime'],
                    'threshold' => $this->alertThresholds['uptime_threshold'],
                ],
            ];
        }

        return $alerts;
    }

    protected function generateSystemAlerts(array $summary): array
    {
        $alerts = [];

        // System critical alert
        if ($summary['overall_status'] === 'critical') {
            $alerts[] = [
                'type' => 'system_critical',
                'severity' => 'critical',
                'plugin' => 'system',
                'title' => 'Plugin System in Critical State',
                'message' => "The plugin system has {$summary['critical_plugins']} critical plugins.",
                'details' => $summary,
            ];
        }

        // Multiple plugins down
        if ($summary['critical_plugins'] >= 3) {
            $alerts[] = [
                'type' => 'multiple_plugins_down',
                'severity' => 'critical',
                'plugin' => 'system',
                'title' => 'Multiple Plugins Are Down',
                'message' => "{$summary['critical_plugins']} plugins are currently in critical state.",
                'details' => $summary,
            ];
        }

        return $alerts;
    }

    protected function shouldSendAlert(string $pluginName, string $alertType): bool
    {
        $cacheKey = $this->cachePrefix . $pluginName . '_' . $alertType;
        $lastSent = Cache::get($cacheKey);

        if (!$lastSent) {
            return true;
        }

        // Don't send same alert more than once per hour
        return now()->diffInMinutes($lastSent) >= 60;
    }

    protected function recordAlertSent(string $pluginName, string $alertType): void
    {
        $cacheKey = $this->cachePrefix . $pluginName . '_' . $alertType;
        Cache::put($cacheKey, now(), now()->addHours(2));
    }

    protected function sendAlert(array $alert): void
    {
        foreach ($this->alertChannels as $channel) {
            match($channel) {
                'log' => $this->sendLogAlert($alert),
                'email' => $this->sendEmailAlert($alert),
                'slack' => $this->sendSlackAlert($alert),
                'webhook' => $this->sendWebhookAlert($alert),
                default => null,
            };
        }
    }

    protected function sendLogAlert(array $alert): void
    {
        $logLevel = match($alert['severity']) {
            'critical' => 'critical',
            'warning' => 'warning',
            default => 'info',
        };

        Log::$logLevel($alert['title'], [
            'plugin' => $alert['plugin'],
            'type' => $alert['type'],
            'message' => $alert['message'],
            'details' => $alert['details'],
        ]);
    }

    protected function sendEmailAlert(array $alert): void
    {
        $recipients = config('laravel-plugin-system.health_monitoring.email_recipients', []);

        if (empty($recipients)) {
            return;
        }

        try {
            Mail::send('plugin-health-alert', $alert, function ($message) use ($alert, $recipients) {
                $message->to($recipients)
                        ->subject($alert['title']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to send health alert email', [
                'alert' => $alert,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendSlackAlert(array $alert): void
    {
        $webhookUrl = config('laravel-plugin-system.health_monitoring.slack_webhook_url');

        if (!$webhookUrl) {
            return;
        }

        $color = match($alert['severity']) {
            'critical' => 'danger',
            'warning' => 'warning',
            default => 'good',
        };

        $payload = [
            'text' => $alert['title'],
            'attachments' => [
                [
                    'color' => $color,
                    'fields' => [
                        [
                            'title' => 'Plugin',
                            'value' => $alert['plugin'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Severity',
                            'value' => strtoupper($alert['severity']),
                            'short' => true,
                        ],
                        [
                            'title' => 'Message',
                            'value' => $alert['message'],
                            'short' => false,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert', [
                'alert' => $alert,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendWebhookAlert(array $alert): void
    {
        $webhookUrl = config('laravel-plugin-system.health_monitoring.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        try {
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($alert));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            Log::error('Failed to send webhook alert', [
                'alert' => $alert,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
