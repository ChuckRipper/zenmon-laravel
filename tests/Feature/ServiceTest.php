<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\{User, Host, Metric, MetricType, Alert, AlertThreshold};
use App\Services\{AlertService, NotificationService};
use Illuminate\Support\Facades\{Log, Queue, Mail};

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $testUser;
    private Host $testHost;
    private array $metricTypes;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testUser = $this->createTestUser();
        $this->testHost = $this->createTestHost();
        $this->metricTypes = $this->createTestMetricTypes();
    }

    #region AlertService Tests

    /// <summary>
    /// Test AlertService correctly determines alert levels
    /// </summary>
    public function test_alert_service_determines_correct_alert_levels(): void
    {
        Queue::fake();
        Mail::fake();

        // Create threshold
        $threshold = $this->createTestAlertThreshold(
            $this->testHost, 
            $this->metricTypes['cpu'], 
            $this->testUser
        );

        $notificationService = new NotificationService();
        $alertService = new AlertService($notificationService);

        // Test Warning level (85% - between 80% and 90%)
        $warningMetric = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'value' => 85.0,
            'timestamp' => now()
        ]);

        $warningAlert = $alertService->checkMetricThresholds($warningMetric);
        $this->assertEquals('Warning', $warningAlert->alert_level);

        // Test Critical level (95% - above 90%)
        $criticalMetric = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'value' => 95.0,
            'timestamp' => now()
        ]);

        $criticalAlert = $alertService->checkMetricThresholds($criticalMetric);
        $this->assertEquals('Critical', $criticalAlert->alert_level);

        // Test no alert (70% - below warning threshold)
        $normalMetric = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'value' => 70.0,
            'timestamp' => now()
        ]);

        $noAlert = $alertService->checkMetricThresholds($normalMetric);
        $this->assertNull($noAlert);
    }

    /// <summary>
    /// Test AlertService handles global thresholds
    /// </summary>
    public function test_alert_service_handles_global_thresholds(): void
    {
        Queue::fake();
        Mail::fake();

        // Create global threshold (host_id = null)
        $globalThreshold = AlertThreshold::create([
            'host_id' => null, // Global
            'metric_type_id' => $this->metricTypes['ram']->metric_type_id,
            'warning_threshold' => 75.0,
            'critical_threshold' => 90.0,
            'is_active' => true,
            'created_by_user_id' => $this->testUser->user_id
        ]);

        $notificationService = new NotificationService();
        $alertService = new AlertService($notificationService);

        $metric = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->metricTypes['ram']->metric_type_id,
            'value' => 80.0,
            'timestamp' => now()
        ]);

        $alert = $alertService->checkMetricThresholds($metric);

        $this->assertNotNull($alert);
        $this->assertEquals('Warning', $alert->alert_level);
        $this->assertEquals(75.0, $alert->threshold_value);
    }

    /// <summary>
    /// Test AlertService prevents duplicate alerts
    /// </summary>
    public function test_alert_service_prevents_duplicate_alerts(): void
    {
        Queue::fake();
        Mail::fake();

        $threshold = $this->createTestAlertThreshold(
            $this->testHost, 
            $this->metricTypes['cpu'], 
            $this->testUser
        );

        // Create existing active alert
        $existingAlert = Alert::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => 'CPU usage high',
            'current_value' => 85.0,
            'threshold_value' => 80.0,
            'status' => 'Active'
        ]);

        $notificationService = new NotificationService();
        $alertService = new AlertService($notificationService);

        // Submit another metric that would trigger warning
        $metric = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'value' => 87.0,
            'timestamp' => now()
        ]);

        $newAlert = $alertService->checkMetricThresholds($metric);

        // Should update existing alert, not create new one
        $this->assertNotNull($newAlert);
        $this->assertEquals($existingAlert->alert_id, $newAlert->alert_id);
        $this->assertEquals(87.0, $newAlert->current_value);
    }

    #endregion

    #region NotificationService Tests

    /// <summary>
    /// Test NotificationService determines correct channels
    /// </summary>
    public function test_notification_service_determines_correct_channels(): void
    {
        $notificationService = new NotificationService();

        $criticalAlert = Alert::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'alert_level' => 'Critical',
            'alert_message' => 'Critical alert',
            'current_value' => 95.0,
            'threshold_value' => 90.0,
            'status' => 'Active'
        ]);

        $warningAlert = Alert::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->metricTypes['ram']->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => 'Warning alert',
            'current_value' => 85.0,
            'threshold_value' => 80.0,
            'status' => 'Active'
        ]);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($notificationService);
        $method = $reflection->getMethod('getDefaultChannels');
        $method->setAccessible(true);

        $criticalChannels = $method->invoke($notificationService, $criticalAlert);
        $warningChannels = $method->invoke($notificationService, $warningAlert);

        // Critical alerts should use more channels
        $this->assertContains('email', $criticalChannels);
        $this->assertContains('slack', $criticalChannels);

        // Warning alerts might use fewer channels
        $this->assertContains('email', $warningChannels);
    }

    #endregion
}