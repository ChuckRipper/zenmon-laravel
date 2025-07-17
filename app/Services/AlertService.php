<?php

namespace App\Services;

use App\Models\{Metric, Alert, AlertThreshold, Host, MetricType};
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/// <summary>
/// Service for automatic alert generation based on metric thresholds (UC41)
/// Monitors incoming metrics and creates alerts when thresholds are exceeded
/// </summary>
class AlertService
{
    #region Properties
    
    /// <summary>
    /// Notification service for sending alerts
    /// </summary>
    private NotificationService $notificationService;
    
    #endregion
    
    #region Constructor
    
    /// <summary>
    /// Initialize AlertService with NotificationService
    /// </summary>
    /// <param>NotificationService $notificationService</param>
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    #endregion
    
    #region Methods

    /// <summary>
    /// Check metric against configured thresholds and generate alerts if needed
    /// </summary>
    /// <param name="Metric">$metric - The metric to check</param>
    /// <returns>Alert|null - Created alert or null if no threshold exceeded</returns>
    public function checkMetricThresholds(Metric $metric): ?Alert
    {
        try {
            // Get threshold for this metric type and host
            $threshold = $this->getApplicableThreshold($metric);
            
            if (!$threshold) {
                // No threshold configured - no alert needed
                return null;
            }

            // Check if metric value exceeds thresholds
            $alertLevel = $this->determineAlertLevel($metric->value, $threshold);
            
            if (!$alertLevel) {
                // Value is within acceptable limits
                $this->closeExistingAlertsIfResolved($metric, $threshold);
                return null;
            }

            // Check if active alert already exists for this metric type and host
            $existingAlert = $this->getExistingActiveAlert($metric);
            
            if ($existingAlert) {
                // Alert already exists - update if level changed
                return $this->updateExistingAlertIfNeeded($existingAlert, $alertLevel, $metric);
            }

            // Create new alert
            return $this->createNewAlert($metric, $threshold, $alertLevel);

        } catch (\Exception $e) {
            Log::error('AlertService: Error checking metric thresholds', [
                'metric_id' => $metric->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /// <summary>
    /// Process multiple metrics at once (for batch operations)
    /// </summary>
    /// <param name="array">$metrics - Array of Metric objects</param>
    /// <returns>array - Array of created alerts</returns>
    public function checkMultipleMetrics(array $metrics): array
    {
        $createdAlerts = [];
        
        foreach ($metrics as $metric) {
            $alert = $this->checkMetricThresholds($metric);
            if ($alert) {
                $createdAlerts[] = $alert;
            }
        }
        
        Log::info('AlertService: Batch check completed', [
            'metrics_checked' => count($metrics),
            'alerts_created' => count($createdAlerts)
        ]);
        
        return $createdAlerts;
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get applicable threshold for metric (host-specific or global)
    /// </summary>
    /// <param name="Metric">$metric</param>
    /// <returns>AlertThreshold|null</returns>
    private function getApplicableThreshold(Metric $metric): ?AlertThreshold
    {
        // First try to find host-specific threshold
        $hostThreshold = AlertThreshold::where('metric_type_id', $metric->metric_type_id)
                                      ->where('host_id', $metric->host_id)
                                      ->where('is_active', true)
                                      ->first();

        if ($hostThreshold) {
            return $hostThreshold;
        }

        // Fall back to global threshold (host_id = null)
        return AlertThreshold::where('metric_type_id', $metric->metric_type_id)
                            ->whereNull('host_id')
                            ->where('is_active', true)
                            ->first();
    }

    /// <summary>
    /// Determine alert level based on metric value and thresholds
    /// </summary>
    /// <param name="float">$value</param>
    /// <param name="AlertThreshold">$threshold</param>
    /// <returns>string|null - 'Warning', 'Critical', or null</returns>
    private function determineAlertLevel(float $value, AlertThreshold $threshold): ?string
    {
        if ($value >= $threshold->critical_threshold) {
            return 'Critical';
        }
        
        if ($value >= $threshold->warning_threshold) {
            return 'Warning';
        }
        
        return null; // Value is OK
    }

    /// <summary>
    /// Get existing active alert for same metric type and host
    /// </summary>
    /// <param name="Metric">$metric</param>
    /// <returns>Alert|null</returns>
    private function getExistingActiveAlert(Metric $metric): ?Alert
    {
        return Alert::where('host_id', $metric->host_id)
                   ->where('metric_type_id', $metric->metric_type_id)
                   ->where('status', 'Active')
                   ->first();
    }

    /// <summary>
    /// Create new alert for threshold violation
    /// </summary>
    /// <param name="Metric">$metric</param>
    /// <param name="AlertThreshold">$threshold</param>
    /// <param name="string">$alertLevel</param>
    /// <returns>Alert</returns>
    private function createNewAlert(Metric $metric, AlertThreshold $threshold, string $alertLevel): Alert
    {
        $alert = Alert::create([
            'host_id' => $metric->host_id,
            'metric_type_id' => $metric->metric_type_id,
            'status' => 'Active',
            'alert_level' => $alertLevel,
            'current_value' => $metric->value,
            'threshold_value' => $alertLevel === 'Critical' 
                ? $threshold->critical_threshold 
                : $threshold->warning_threshold,
            'alert_message' => $this->generateAlertMessage($metric, $threshold, $alertLevel)
        ]);

        Log::warning('AlertService: New alert created', [
            'alert_id' => $alert->id,
            'host_id' => $metric->host_id,
            'metric_type' => $metric->metricType->metric_name ?? 'Unknown',
            'level' => $alertLevel,
            'value' => $metric->value,
            'threshold' => $alertLevel === 'Critical' 
                ? $threshold->critical_threshold 
                : $threshold->warning_threshold
        ]);

        // 🚨 SEND NOTIFICATION FOR NEW ALERT
        try {
            $alert->load(['host', 'metricType']); // Load relationships for notifications
            $this->notificationService->sendAlertNotification($alert);
            Log::info('Alert notification triggered', ['alert_id' => $alert->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send alert notification', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage()
            ]);
        }

        return $alert;
    }

    /// <summary>
    /// Update existing alert if severity level changed
    /// </summary>
    /// <param name="Alert">$existingAlert</param>
    /// <param name="string">$newAlertLevel</param>
    /// <param name="Metric">$metric</param>
    /// <returns>Alert</returns>
    private function updateExistingAlertIfNeeded(Alert $existingAlert, string $newAlertLevel, Metric $metric): Alert
    {
        $updates = [
            'current_value' => $metric->value,
            // 'updated_at' => now()
        ];
        
        // Jeśli zmienił się poziom alertu
        if ($existingAlert->alert_level !== $newAlertLevel) {
            $updates['alert_level'] = $newAlertLevel;
            // $updates['alert_message'] = $this->generateAlertMessage($metric, $existingAlert->threshold, $newAlertLevel);
            $threshold = $this->getApplicableThreshold($metric);
            $updates['alert_message'] = $this->generateAlertMessage($metric, $threshold, $newAlertLevel);
            
            Log::info('AlertService: Alert level changed', [
                'alert_id' => $existingAlert->alert_id,
                'old_level' => $existingAlert->alert_level,
                'new_level' => $newAlertLevel,
                'host_id' => $metric->host_id
            ]);
        }
        
        $existingAlert->update($updates);
        
        Log::debug('AlertService: Alert updated with new value', [
            'alert_id' => $existingAlert->alert_id,
            'new_value' => $metric->value,
            'host_id' => $metric->host_id
        ]);

        return $existingAlert;
    }

    /// <summary>
    /// Close existing alerts if metric value returned to normal
    /// </summary>
    /// <param name="Metric">$metric</param>
    /// <param name="AlertThreshold">$threshold</param>
    /// <returns>void</returns>
    private function closeExistingAlertsIfResolved(Metric $metric, AlertThreshold $threshold): void
    {
        $activeAlerts = Alert::where('host_id', $metric->host_id)
                            ->where('metric_type_id', $metric->metric_type_id)
                            ->where('status', 'Active')
                            ->get();

        foreach ($activeAlerts as $alert) {
            // Check if current value is below warning threshold
            if ($metric->value < $threshold->warning_threshold) {
                $alert->update([
                    'status' => 'Resolved',
                    'resolved_date' => now(),
                    'last_updated' => now(),
                    'resolution_comment' => 'Automatically resolved - metric value returned to normal'
                ]);

                Log::info('AlertService: Alert automatically resolved', [
                    'alert_id' => $alert->id,
                    'current_value' => $metric->value,
                    'warning_threshold' => $threshold->warning_threshold
                ]);

                // 🚨 SEND RESOLVED NOTIFICATION
                try {
                    $alert->load(['host', 'metricType']); // Load relationships for notifications
                    $this->notificationService->sendAlertResolvedNotification($alert);
                    Log::info('Alert resolved notification triggered', ['alert_id' => $alert->id]);
                } catch (\Exception $e) {
                    Log::error('Failed to send resolved notification', [
                        'alert_id' => $alert->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /// <summary>
    /// Generate human-readable alert message
    /// </summary>
    /// <param name="Metric">$metric</param>
    /// <param name="AlertThreshold">$threshold</param>
    /// <param name="string">$alertLevel</param>
    /// <returns>string</returns>
    private function generateAlertMessage(Metric $metric, AlertThreshold $threshold, string $alertLevel): string
    {
        $metricType = $metric->metricType->metric_name ?? 'Unknown';
        $hostName = $metric->host->host_name ?? 'Unknown';
        $unit = $metric->metricType->unit ?? '';
        
        $thresholdValue = $alertLevel === 'Critical' 
            ? $threshold->critical_threshold 
            : $threshold->warning_threshold;

        return sprintf(
            '%s alert: %s on host %s reached %s%s (threshold: %s%s)',
            $alertLevel,
            $metricType,
            $hostName,
            $metric->value,
            $unit,
            $thresholdValue,
            $unit
        );
    }

    #endregion
}