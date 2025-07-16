<?php

namespace App\Mail;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

/// <summary>
/// Mailable class for sending alert notifications via email
/// Creates beautifully formatted HTML emails with alert details
/// </summary>
class AlertNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    #region Properties
    
    /// <summary>
    /// Alert to send notification about
    /// </summary>
    public Alert $alert;
    
    /// <summary>
    /// Type of notification (new, resolved, test)
    /// </summary>
    public string $type;
    
    #endregion
    
    #region Constructor
    
    /// <summary>
    /// Create a new message instance
    /// </summary>
    /// <param>Alert $alert</param>
    /// <param>string $type</param>
    public function __construct(Alert $alert, string $type = 'new')
    {
        $this->alert = $alert;
        $this->type = $type;
        
        // Ensure relationships are loaded
        if (!$alert->relationLoaded('host')) {
            $alert->load(['host', 'metricType']);
        }
    }
    
    #endregion
    
    #region Methods
    
    /// <summary>
    /// Get the message envelope
    /// </summary>
    /// <returns>Envelope</returns>
    public function envelope(): Envelope
    {
        $subject = $this->buildSubject();
        
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'noreply@zenmon.local'),
                config('mail.from.name', 'ZenMon Alert System')
            ),
            subject: $subject,
            replyTo: [
                new Address(config('mail.from.address', 'noreply@zenmon.local'), 'ZenMon Support')
            ],
            tags: [
                'alert-notification',
                $this->type,
                $this->alert->alert_level,
                'host:' . $this->alert->host->hostname
            ]
        );
    }

    /// <summary>
    /// Get the message content definition
    /// </summary>
    /// <returns>Content</returns>
    public function content(): Content
    {
        return new Content(
            view: 'emails.alert-notification',
            text: 'emails.alert-notification-text',
            with: [
                'alert' => $this->alert,
                'type' => $this->type,
                'alertUrl' => $this->buildAlertUrl(),
                'dashboardUrl' => $this->buildDashboardUrl(),
                'priorityLevel' => $this->getPriorityLevel(),
                'colorScheme' => $this->getColorScheme(),
                'actionText' => $this->getActionText(),
                'systemInfo' => $this->getSystemInfo()
            ]
        );
    }

    /// <summary>
    /// Get the attachments for the message
    /// </summary>
    /// <returns>array</returns>
    public function attachments(): array
    {
        return [];
    }
    
    /// <summary>
    /// Build email subject line
    /// </summary>
    /// <returns>string</returns>
    protected function buildSubject(): string
    {
        $hostname = $this->alert->host->hostname;
        $metricType = $this->alert->metricType->type_name;
        
        return match($this->type) {
            'resolved' => "✅ [ZenMon] RESOLVED: {$hostname} - {$metricType}",
            'test' => "🧪 [ZenMon] TEST NOTIFICATION",
            default => match($this->alert->alert_level) {
                'Critical' => "🔥 [ZenMon] CRITICAL: {$hostname} - {$metricType}",
                'Warning' => "⚠️ [ZenMon] WARNING: {$hostname} - {$metricType}",
                default => "📊 [ZenMon] ALERT: {$hostname} - {$metricType}"
            }
        };
    }
    
    /// <summary>
    /// Build URL to view alert details
    /// </summary>
    /// <returns>string</returns>
    private function buildAlertUrl(): string
    {
        return config('app.url') . "/alerts/{$this->alert->alert_id}";
    }
    
    /// <summary>
    /// Build URL to dashboard
    /// </summary>
    /// <returns>string</returns>
    private function buildDashboardUrl(): string
    {
        return config('app.url') . "/alerts";
    }
    
    /// <summary>
    /// Get priority level for email styling
    /// </summary>
    /// <returns>string</returns>
    private function getPriorityLevel(): string
    {
        if ($this->type === 'resolved') {
            return 'success';
        }
        
        return match($this->alert->alert_level) {
            'Critical' => 'critical',
            'Warning' => 'warning',
            default => 'info'
        };
    }
    
    /// <summary>
    /// Get color scheme for email styling
    /// </summary>
    /// <returns>array</returns>
    private function getColorScheme(): array
    {
        return match($this->getPriorityLevel()) {
            'critical' => [
                'primary' => '#dc3545',
                'secondary' => '#f8d7da',
                'text' => '#721c24',
                'bg' => '#ffffff'
            ],
            'warning' => [
                'primary' => '#ffc107',
                'secondary' => '#fff3cd',
                'text' => '#856404',
                'bg' => '#ffffff'
            ],
            'success' => [
                'primary' => '#28a745',
                'secondary' => '#d4edda',
                'text' => '#155724',
                'bg' => '#ffffff'
            ],
            default => [
                'primary' => '#007bff',
                'secondary' => '#d1ecf1',
                'text' => '#0c5460',
                'bg' => '#ffffff'
            ]
        };
    }
    
    /// <summary>
    /// Get action text for buttons
    /// </summary>
    /// <returns>array</returns>
    private function getActionText(): array
    {
        return [
            'primary' => $this->type === 'resolved' ? 'View Alert History' : 'View Alert Details',
            'secondary' => 'Open Dashboard'
        ];
    }
    
    /// <summary>
    /// Get system information for email footer
    /// </summary>
    /// <returns>array</returns>
    private function getSystemInfo(): array
    {
        return [
            'app_name' => config('app.name', 'ZenMon'),
            'app_url' => config('app.url'),
            'timestamp' => now()->format('Y-m-d H:i:s T'),
            'alert_id' => $this->alert->alert_id,
            'environment' => config('app.env', 'production')
        ];
    }
    
    #endregion
}