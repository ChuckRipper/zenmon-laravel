<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\User;
use App\Models\Host;
use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendSlackNotificationJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

/// <summary>
/// Service for sending notifications about alerts (Email, Slack, SMS)
/// Handles all notification channels for ZenMon alerts
/// </summary>
class NotificationService
{
    #region Constants
    
    /// <summary>
    /// Available notification channels
    /// </summary>
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SLACK = 'slack';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_WEBHOOK = 'webhook';
    
    /// <summary>
    /// Alert levels that trigger notifications
    /// </summary>
    const NOTIFY_LEVELS = ['Warning', 'Critical'];
    
    /// <summary>
    /// Alert statuses that trigger notifications
    /// </summary>
    const NOTIFY_STATUSES = ['Active', 'Resolved'];
    
    #endregion
    
    #region Methods
    
    /// <summary>
    /// Send notification for new alert
    /// Called from AlertService when alert is created
    /// </summary>
    /// <param>Alert $alert</param>
    /// <param>array $channels</param>
    /// <returns>bool</returns>
    public function sendAlertNotification(Alert $alert, array $channels = null): bool
    {
        try {
            // Default channels if not specified
            if ($channels === null) {
                $channels = $this->getDefaultChannels($alert);
            }
            
            Log::info('Sending alert notification', [
                'alert_id' => $alert->alert_id,
                'host' => $alert->host->host_name,
                'level' => $alert->alert_level,
                'channels' => $channels
            ]);
            
            $success = true;
            
            // Send to each channel
            foreach ($channels as $channel) {
                try {
                    switch ($channel) {
                        case self::CHANNEL_EMAIL:
                            $this->sendEmailNotification($alert);
                            break;
                            
                        case self::CHANNEL_SLACK:
                            $this->sendSlackNotification($alert);
                            break;
                            
                        case self::CHANNEL_SMS:
                            $this->sendSmsNotification($alert);
                            break;
                            
                        case self::CHANNEL_WEBHOOK:
                            $this->sendWebhookNotification($alert);
                            break;
                            
                        default:
                            Log::warning('Unknown notification channel', ['channel' => $channel]);
                            $success = false;
                    }
                } catch (Exception $e) {
                    Log::error("Failed to send notification via {$channel}", [
                        'alert_id' => $alert->alert_id,
                        'channel' => $channel,
                        'error' => $e->getMessage()
                    ]);
                    $success = false;
                }
            }
            
            return $success;
            
        } catch (Exception $e) {
            Log::error('NotificationService@sendAlertNotification failed', [
                'alert_id' => $alert->alert_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /// <summary>
    /// Send notification when alert is resolved
    /// </summary>
    /// <param>Alert $alert</param>
    /// <param>array $channels</param>
    /// <returns>bool</returns>
    public function sendAlertResolvedNotification(Alert $alert, array $channels = null): bool
    {
        try {
            // Only send resolved notifications for Critical alerts
            if ($alert->alert_level !== 'Critical') {
                return true;
            }
            
            if ($channels === null) {
                $channels = $this->getDefaultChannels($alert);
            }
            
            Log::info('Sending alert resolved notification', [
                'alert_id' => $alert->alert_id,
                'host' => $alert->host->host_name,
                'channels' => $channels
            ]);
            
            $success = true;
            
            foreach ($channels as $channel) {
                try {
                    switch ($channel) {
                        case self::CHANNEL_EMAIL:
                            $this->sendEmailNotification($alert, 'resolved');
                            break;
                            
                        case self::CHANNEL_SLACK:
                            $this->sendSlackNotification($alert, 'resolved');
                            break;
                            
                        default:
                            // SMS and webhook only for new alerts, not resolved
                            break;
                    }
                } catch (Exception $e) {
                    Log::error("Failed to send resolved notification via {$channel}", [
                        'alert_id' => $alert->alert_id,
                        'channel' => $channel,
                        'error' => $e->getMessage()
                    ]);
                    $success = false;
                }
            }
            
            return $success;
            
        } catch (Exception $e) {
            Log::error('NotificationService@sendAlertResolvedNotification failed', [
                'alert_id' => $alert->alert_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /// <summary>
    /// Send email notification (queued for async processing)
    /// </summary>
    /// <param>Alert $alert</param>
    /// <param>string $type</param>
    /// <returns>void</returns>
    private function sendEmailNotification(Alert $alert, string $type = 'new'): void
    {
        $recipients = $this->getEmailRecipients($alert);
        
        if (empty($recipients)) {
            Log::warning('No email recipients configured for alert', [
                'alert_id' => $alert->alert_id,
                'host' => $alert->host->host_name
            ]);
            return;
        }
        
        // Queue email job for async processing
        SendEmailNotificationJob::dispatch($alert, $recipients, $type)
            ->onQueue('notifications')
            ->delay(now()->addSeconds(5)); // 5 second delay to avoid spam
    }
    
    /// <summary>
    /// Send Slack notification (queued for async processing)
    /// </summary>
    /// <param>Alert $alert</param>
    /// <param>string $type</param>
    /// <returns>void</returns>
    private function sendSlackNotification(Alert $alert, string $type = 'new'): void
    {
        $webhookUrl = config('zenmon.slack_webhook_url');
        
        if (empty($webhookUrl)) {
            Log::warning('Slack webhook URL not configured');
            return;
        }
        
        // Queue Slack job for async processing
        SendSlackNotificationJob::dispatch($alert, $webhookUrl, $type)
            ->onQueue('notifications')
            ->delay(now()->addSeconds(2));
    }
    
    /// <summary>
    /// Send SMS notification (placeholder for future implementation)
    /// </summary>
    /// <param>Alert $alert</param>
    /// <returns>void</returns>
    private function sendSmsNotification(Alert $alert): void
    {
        // TODO: Implement SMS notifications (Twilio, AWS SNS, etc.)
        Log::info('SMS notification triggered', [
            'alert_id' => $alert->alert_id,
            'note' => 'SMS functionality not yet implemented'
        ]);
    }
    
    /// <summary>
    /// Send webhook notification (for external integrations)
    /// </summary>
    /// <param>Alert $alert</param>
    /// <returns>void</returns>
    private function sendWebhookNotification(Alert $alert): void
    {
        $webhookUrls = config('zenmon.webhook_urls', []);
        
        if (empty($webhookUrls)) {
            return;
        }
        
        foreach ($webhookUrls as $url) {
            try {
                $payload = [
                    'alert_id' => $alert->alert_id,
                    'host' => $alert->host->host_name,
                    'ip_address' => $alert->host->ip_address,
                    'metric_type' => $alert->metricType->type_name,
                    'alert_level' => $alert->alert_level,
                    'message' => $alert->alert_message,
                    'current_value' => $alert->current_value,
                    'threshold_value' => $alert->threshold_value,
                    'status' => $alert->status,
                    'created_at' => $alert->created_at->toISOString(),
                    'zenmon_url' => config('app.url')
                ];
                
                // Make HTTP POST request
                $response = file_get_contents($url, false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => [
                            'Content-Type: application/json',
                            'User-Agent: ZenMon/1.0'
                        ],
                        'content' => json_encode($payload),
                        'timeout' => 10
                    ]
                ]));
                
                Log::info('Webhook notification sent', [
                    'alert_id' => $alert->alert_id,
                    'webhook_url' => $url
                ]);
                
            } catch (Exception $e) {
                Log::error('Webhook notification failed', [
                    'alert_id' => $alert->alert_id,
                    'webhook_url' => $url,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /// <summary>
    /// Get default notification channels based on alert level
    /// </summary>
    /// <param>Alert $alert</param>
    /// <returns>array</returns>
    private function getDefaultChannels(Alert $alert): array
    {
        $channels = [];
        
        // Always send email for any alert
        $channels[] = self::CHANNEL_EMAIL;
        
        // Add Slack for Warning and Critical
        if (in_array($alert->alert_level, ['Warning', 'Critical'])) {
            $channels[] = self::CHANNEL_SLACK;
        }
        
        // Add webhook for Critical only
        if ($alert->alert_level === 'Critical') {
            $channels[] = self::CHANNEL_WEBHOOK;
        }
        
        // TODO: Add SMS for Critical alerts in production
        // if ($alert->alert_level === 'Critical') {
        //     $channels[] = self::CHANNEL_SMS;
        // }
        
        return $channels;
    }
    
    /// <summary>
    /// Get email recipients for alert notifications
    /// </summary>
    /// <param>Alert $alert</param>
    /// <returns>array</returns>
    private function getEmailRecipients(Alert $alert): array
    {
        $recipients = [];
        
        // Get administrators (always notified)
        $admins = User::where('role', 'Administrator')
                     ->where('is_active', true)
                     ->whereNotNull('email')
                     ->pluck('email')
                     ->toArray();
        
        $recipients = array_merge($recipients, $admins);
        
        // Get users (notified for Critical alerts only)
        if ($alert->alert_level === 'Critical') {
            $users = User::where('role', 'User')
                        ->where('is_active', true)
                        ->whereNotNull('email')
                        ->pluck('email')
                        ->toArray();
            
            $recipients = array_merge($recipients, $users);
        }
        
        // Add configured email addresses from config
        $configEmails = config('zenmon.alert_emails', []);
        $recipients = array_merge($recipients, $configEmails);
        
        // Remove duplicates and empty values
        $recipients = array_unique(array_filter($recipients));
        
        return $recipients;
    }
    
    /// <summary>
    /// Test notification system with sample alert
    /// Used for testing configuration
    /// </summary>
    /// <param>string $channel</param>
    /// <param>string $recipient</param>
    /// <returns>bool</returns>
    public function sendTestNotification(string $channel, string $recipient = null): bool
    {
        try {
            // Create a fake alert for testing
            $testAlert = new Alert([
                // 'alert_id' => 999999,
                'alert_level' => 'Critical',
                'alert_message' => 'This is a test notification from ZenMon',
                'current_value' => 95.5,
                'threshold_value' => 90.0,
                'status' => 'Active',
                // 'created_at' => now()
            ]);

            $testAlert->alert_id = 999999;
            $testAlert->created_at = now();
            $testAlert->updated_at = now();
            
            // Create fake relationships
            $testAlert->setRelation('host', (object)[
                'host_name' => 'test-server',
                'ip_address' => '192.168.1.100'
            ]);
            
            $testAlert->setRelation('metricType', (object)[
                'type_name' => 'CPU Usage'
            ]);
            
            switch ($channel) {
                case self::CHANNEL_EMAIL:
                    if ($recipient) {
                        SendEmailNotificationJob::dispatch($testAlert, [$recipient], 'test')
                            ->onQueue('notifications');
                    } else {
                        $this->sendEmailNotification($testAlert);
                    }
                    break;
                    
                case self::CHANNEL_SLACK:
                    $this->sendSlackNotification($testAlert);
                    break;
                    
                default:
                    throw new Exception("Test not supported for channel: {$channel}");
            }
            
            Log::info('Test notification sent', [
                'channel' => $channel,
                'recipient' => $recipient
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Test notification failed', [
                'channel' => $channel,
                'recipient' => $recipient,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    #endregion
}