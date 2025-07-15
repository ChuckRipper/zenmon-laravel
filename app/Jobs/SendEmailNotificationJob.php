<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Mail\AlertNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

/// <summary>
/// Queue job for sending email notifications about alerts
/// Handles async email delivery to avoid blocking main application
/// </summary>
class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    #region Properties
    
    /// <summary>
    /// Alert to send notification about
    /// </summary>
    public Alert $alert;
    
    /// <summary>
    /// Email recipients array
    /// </summary>
    public array $recipients;
    
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
    public int $timeout = 60;
    
    #endregion
    
    #region Constructor
    
    /// <summary>
    /// Create a new job instance
    /// </summary>
    /// <param>Alert $alert</param>
    /// <param>array $recipients</param>
    /// <param>string $type</param>
    public function __construct(Alert $alert, array $recipients, string $type = 'new')
    {
        $this->alert = $alert;
        $this->recipients = $recipients;
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
            
            Log::info('Processing email notification job', [
                'alert_id' => $this->alert->alert_id,
                'recipients_count' => count($this->recipients),
                'type' => $this->type,
                'attempt' => $this->attempts()
            ]);
            
            // Validate recipients
            $validRecipients = $this->validateRecipients();
            
            if (empty($validRecipients)) {
                Log::warning('No valid email recipients for alert notification', [
                    'alert_id' => $this->alert->alert_id,
                    'original_recipients' => $this->recipients
                ]);
                return;
            }
            
            // Send email to each recipient
            foreach ($validRecipients as $recipient) {
                try {
                    Mail::to($recipient)->send(new AlertNotificationMail($this->alert, $this->type));
                    
                    Log::info('Email notification sent successfully', [
                        'alert_id' => $this->alert->alert_id,
                        'recipient' => $recipient,
                        'type' => $this->type
                    ]);
                    
                    // Small delay between emails to avoid overwhelming mail server
                    if (count($validRecipients) > 1) {
                        usleep(100000); // 100ms delay
                    }
                    
                } catch (Exception $e) {
                    Log::error('Failed to send email to recipient', [
                        'alert_id' => $this->alert->alert_id,
                        'recipient' => $recipient,
                        'error' => $e->getMessage(),
                        'attempt' => $this->attempts()
                    ]);
                    
                    // If this is the last attempt, don't throw exception for individual failures
                    if ($this->attempts() >= $this->tries) {
                        continue;
                    }
                    
                    throw $e; // Re-throw to trigger job retry
                }
            }
            
            Log::info('Email notification job completed successfully', [
                'alert_id' => $this->alert->alert_id,
                'recipients_sent' => count($validRecipients),
                'type' => $this->type
            ]);
            
        } catch (Exception $e) {
            Log::error('SendEmailNotificationJob failed', [
                'alert_id' => $this->alert->alert_id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries
            ]);
            
            // If this is the last attempt, log final failure
            if ($this->attempts() >= $this->tries) {
                Log::critical('Email notification job failed permanently', [
                    'alert_id' => $this->alert->alert_id,
                    'recipients' => $this->recipients,
                    'error' => $e->getMessage()
                ]);
            }
            
            throw $e; // Re-throw to trigger retry or final failure
        }
    }
    
    /// <summary>
    /// Handle job failure
    /// </summary>
    /// <param>Exception $exception</param>
    /// <returns>void</returns>
    public function failed(Exception $exception): void
    {
        Log::critical('SendEmailNotificationJob failed permanently', [
            'alert_id' => $this->alert->alert_id,
            'recipients' => $this->recipients,
            'type' => $this->type,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
        
        // TODO: Could implement fallback notification mechanism here
        // For example, save to database for manual retry or use different channel
    }
    
    /// <summary>
    /// Validate and filter email recipients
    /// </summary>
    /// <returns>array</returns>
    private function validateRecipients(): array
    {
        $validRecipients = [];
        
        foreach ($this->recipients as $recipient) {
            // Trim whitespace
            $recipient = trim($recipient);
            
            // Skip empty values
            if (empty($recipient)) {
                continue;
            }
            
            // Validate email format
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Invalid email address in notification recipients', [
                    'alert_id' => $this->alert->alert_id,
                    'invalid_email' => $recipient
                ]);
                continue;
            }
            
            $validRecipients[] = $recipient;
        }
        
        // Remove duplicates
        return array_unique($validRecipients);
    }
    
    /// <summary>
    /// Get the retry delay in seconds
    /// Exponential backoff: 1min, 5min, 15min
    /// </summary>
    /// <returns>int</returns>
    public function retryAfter(): int
    {
        return match($this->attempts()) {
            1 => 60,      // 1 minute
            2 => 300,     // 5 minutes  
            default => 900 // 15 minutes
        };
    }
    
    /// <summary>
    /// Get unique job ID for preventing duplicates
    /// </summary>
    /// <returns>string</returns>
    public function uniqueId(): string
    {
        return "email_notification_{$this->alert->alert_id}_{$this->type}_" . md5(implode(',', $this->recipients));
    }
    
    /// <summary>
    /// Get tags for monitoring and debugging
    /// </summary>
    /// <returns>array</returns>
    public function tags(): array
    {
        return [
            'email-notification',
            "alert:{$this->alert->alert_id}",
            "level:{$this->alert->alert_level}",
            "type:{$this->type}",
            "host:{$this->alert->host->hostname ?? 'unknown'}"
        ];
    }
    
    #endregion
}