<?php

namespace App\Jobs;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/// <summary>
/// Queue job for sending Slack notifications about alerts
/// Handles async Slack webhook delivery
/// </summary>
class SendSlackNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    #region Properties
    
    /// <summary>
    /// Alert to send notification about
    /// </summary>
    public Alert $alert;
    
    /// <summary>
    /// Slack webhook URL
    /// </summary>
    public string $webhookUrl;
    
    /// <summary>
    /// Notification type (new, resolved, test)
    /// </summary>
    public string $type;
    
    /// <summary>
    /// Number of retry attempts
    /// </summary>
    public int $tries = 3;
    
    /// <summary>
    /// Timeout in seconds
    /// </summary>
    public int $timeout = 30;
    
    #endregion
    
    #region Constructor
    
    /// <summary>
    /// Create a new job instance
    /// </summary>
    /// <param>Alert $alert</param>
    /// <param>string $webhookUrl</param>
    /// <param>string $type</param>
    public function __construct(Alert $alert, string $webhookUrl, string $type = 'new')
    {
        $this->alert = $alert;
        $this->webhookUrl = $webhookUrl;
        $this->type = $type;
        
        // Set queue priority based on alert level
        if ($alert->alert_level === 'Critical') {
            $this->onQueue('critical-notifications');
        } else {
            $this->onQueue('notifications');
        }
    }
    
    #endregion
    
    #region Methods
    
    /// <summary>
    /// Execute the job
    /// </summary>
    /// <returns>void</returns>
    public function handle(): void
    {
        try {
            // Load alert relationships if not already loaded
            if (!$this->alert->relationLoaded('host')) {
                $this->alert->load(['host', 'metricType']);
            }
            
            Log::info('Processing Slack notification job', [
                'alert_id' => $this->alert->alert_id,
                'type' => $this->type,
                'attempt' => $this->attempts()
            ]);
            
            // Build Slack message payload
            $payload = $this->buildSlackPayload();
            
            // Send to Slack webhook
            $response = Http::timeout(30)
                          ->retry(2, 1000) // 2 retries with 1 second delay
                          ->post($this->webhookUrl, $payload);
            
            if ($response->successful()) {
                Log::info('Slack notification sent successfully', [
                    'alert_id' => $this->alert->alert_id,
                    'type' => $this->type,
                    'response_status' => $response->status()
                ]);
            } else {
                throw new Exception("Slack webhook returned status {$response->status()}: {$response->body()}");
            }
            
        } catch (Exception $e) {
            Log::error('SendSlackNotificationJob failed', [
                'alert_id' => $this->alert->alert_id,
                'webhook_url' => $this->webhookUrl,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries
            ]);
            
            if ($this->attempts() >= $this->tries) {
                Log::critical('Slack notification job failed permanently', [
                    'alert_id' => $this->alert->alert_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            throw $e; // Re-throw to trigger retry
        }
    }
    
    /// <summary>
    /// Handle job failure
    /// </summary>
    /// <param>Exception $exception</param>
    /// <returns>void</returns>
    public function failed(Exception $exception): void
    {
        Log::critical('SendSlackNotificationJob failed permanently', [
            'alert_id' => $this->alert->alert_id,
            'webhook_url' => $this->webhookUrl,
            'type' => $this->type,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
    
    /// <summary>
    /// Build Slack message payload
    /// </summary>
    /// <returns>array</returns>
    private function buildSlackPayload(): array
    {
        $host = $this->alert->host;
        $metricType = $this->alert->metricType;
        
        // Color based on alert level and type
        $color = $this->getAlertColor();
        
        // Icon and title based on type
        [$icon, $title] = $this->getAlertTitleAndIcon();
        
        // Build main message
        $text = $this->type === 'resolved' 
            ? "✅ Alert Resolved: {$host->hostname}"
            : "🚨 {$this->alert->alert_level} Alert: {$host->hostname}";
            
        // Build attachment with detailed info
        $attachment = [
            'color' => $color,
            'title' => $title,
            'fields' => $this->buildSlackFields(),
            'footer' => 'ZenMon Alert System',
            'footer_icon' => 'https://cdn-icons-png.flaticon.com/512/1828/1828270.png',
            'ts' => $this->alert->created_at->timestamp
        ];
        
        // Add action buttons for active alerts
        if ($this->type === 'new' && $this->alert->status === 'Active') {
            $attachment['actions'] = $this->buildSlackActions();
        }
        
        return [
            'text' => $text,
            'username' => 'ZenMon',
            'icon_emoji' => $icon,
            'attachments' => [$attachment]
        ];
    }
    
    /// <summary>
    /// Get alert color for Slack message
    /// </summary>
    /// <returns>string</returns>
    private function getAlertColor(): string
    {
        if ($this->type === 'resolved') {
            return '#28a745'; // Green
        }
        
        return match($this->alert->alert_level) {
            'Critical' => '#dc3545', // Red
            'Warning' => '#ffc107',  // Yellow
            default => '#6c757d'     // Gray
        };
    }
    
    /// <summary>
    /// Get alert title and icon
    /// </summary>
    /// <returns>array</returns>
    private function getAlertTitleAndIcon(): array
    {
        if ($this->type === 'resolved') {
            return [':white_check_mark:', 'Alert Resolved'];
        }
        
        if ($this->type === 'test') {
            return [':test_tube:', 'Test Notification'];
        }
        
        return match($this->alert->alert_level) {
            'Critical' => [':fire:', 'Critical Alert'],
            'Warning' => [':warning:', 'Warning Alert'],
            default => [':exclamation:', 'Alert']
        };
    }
    
    /// <summary>
    /// Build Slack message fields
    /// </summary>
    /// <returns>array</returns>
    private function buildSlackFields(): array
    {
        $host = $this->alert->host;
        $metricType = $this->alert->metricType;
        
        $fields = [
            [
                'title' => 'Host',
                'value' => "{$host->hostname}\n`{$host->ip_address}`",
                'short' => true
            ],
            [
                'title' => 'Metric',
                'value' => $metricType->type_name,
                'short' => true
            ]
        ];
        
        if ($this->type !== 'resolved') {
            $fields[] = [
                'title' => 'Current Value',
                'value' => number_format($this->alert->current_value, 2),
                'short' => true
            ];
            
            $fields[] = [
                'title' => 'Threshold',
                'value' => number_format($this->alert->threshold_value, 2),
                'short' => true
            ];
        }
        
        $fields[] = [
            'title' => 'Message',
            'value' => $this->alert->alert_message,
            'short' => false
        ];
        
        if ($this->type === 'resolved') {
            $fields[] = [
                'title' => 'Resolution Time',
                'value' => $this->alert->updated_at->diffForHumans($this->alert->created_at),
                'short' => true
            ];
        }
        
        // Add status info for resolved alerts
        if ($this->type === 'resolved' && $this->alert->status === 'Closed' && $this->alert->close_comment) {
            $fields[] = [
                'title' => 'Resolution Comment',
                'value' => $this->alert->close_comment,
                'short' => false
            ];
        }
        
        return $fields;
    }
    
    /// <summary>
    /// Build Slack action buttons
    /// </summary>
    /// <returns>array</returns>
    private function buildSlackActions(): array
    {
        $baseUrl = config('app.url');
        
        return [
            [
                'type' => 'button',
                'text' => 'View Alert',
                'url' => "{$baseUrl}/alerts/{$this->alert->alert_id}",
                'style' => 'primary'
            ],
            [
                'type' => 'button',
                'text' => 'View Dashboard',
                'url' => "{$baseUrl}/alerts",
                'style' => 'default'
            ]
        ];
    }
    
    /// <summary>
    /// Get the retry delay in seconds
    /// </summary>
    /// <returns>int</returns>
    public function retryAfter(): int
    {
        return match($this->attempts()) {
            1 => 30,      // 30 seconds
            2 => 120,     // 2 minutes  
            default => 300 // 5 minutes
        };
    }
    
    /// <summary>
    /// Get unique job ID for preventing duplicates
    /// </summary>
    /// <returns>string</returns>
    public function uniqueId(): string
    {
        return "slack_notification_{$this->alert->alert_id}_{$this->type}_" . md5($this->webhookUrl);
    }
    
    /// <summary>
    /// Get tags for monitoring and debugging
    /// </summary>
    /// <returns>array</returns>
    public function tags(): array
    {
        return [
            'slack-notification',
            "alert:{$this->alert->alert_id}",
            "level:{$this->alert->alert_level}",
            "type:{$this->type}",
            "host:" . (isset($this->alert->host->hostname) ? $this->alert->host->hostname : 'unknown')
        ];
    }
    
    #endregion
}