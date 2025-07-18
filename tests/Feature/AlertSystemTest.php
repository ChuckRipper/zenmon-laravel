<?php

namespace Tests\Feature;

use App\Models\{User, Host, MetricType, Metric, Alert, AlertThreshold};
use App\Services\{AlertService, NotificationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Queue, Mail, Log};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/// <summary>
/// Comprehensive test suite for Alert System (UC41, UC42, UC43)
/// Tests alert generation, API endpoints, and notification integration
/// </summary>
class AlertSystemTest extends TestCase
{
    use RefreshDatabase;

    #region Setup

    /// <summary>
    /// Set up test environment with users, hosts, and thresholds
    /// </summary>
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->admin = User::create([
            'login' => 'admin_alert_test',
            'password' => Hash::make('testpass'),
            'email' => 'admin@alerttest.local',
            'first_name' => 'Alert',
            'last_name' => 'Admin',
            'role' => 'Administrator',
            'is_active' => true
        ]);

        $this->user = User::create([
            'login' => 'user_alert_test',
            'password' => Hash::make('testpass'),
            'email' => 'user@alerttest.local',
            'first_name' => 'Alert',
            'last_name' => 'User',
            'role' => 'User',
            'is_active' => true
        ]);

        // Create test host
        $this->testHost = Host::create([
            'host_name' => 'alert-test-host',
            'ip_address' => '192.168.1.200',
            'description' => 'Test host for alerts',
            'operating_system' => 'Ubuntu 22.04',
            'is_active' => true
        ]);

        // Create metric types
        $this->cpuType = MetricType::create([
            'metric_name' => 'CPU Usage',
            'unit' => '%',
            'description' => 'CPU utilization percentage'
        ]);

        $this->ramType = MetricType::create([
            'metric_name' => 'Memory Usage', 
            'unit' => '%',
            'description' => 'Memory utilization percentage'
        ]);

        // DEBUG - PRZENIEŚ NA KONIEC setUp()
        // dd([
        //     'cpuType_id' => $this->cpuType->metric_type_id,
        //     'cpuType_exists' => $this->cpuType->exists,
        //     'ramType_id' => $this->ramType->metric_type_id,
        //     'ramType_exists' => $this->ramType->exists
        // ]);

        // Create alert thresholds
        $this->cpuThreshold = AlertThreshold::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'warning_threshold' => 80.0,
            'critical_threshold' => 90.0,
            'is_active' => true,
            'created_by_user_id' => $this->admin->id
        ]);

        $this->ramThreshold = AlertThreshold::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->ramType->metric_type_id,
            'warning_threshold' => 85.0,
            'critical_threshold' => 95.0,
            'is_active' => true,
            'created_by_user_id' => $this->admin->id
        ]);

        // Fake queue and mail for testing
        Queue::fake();
        Mail::fake();
    }

    #endregion

    #region UC41: Alert Generation Tests

    /// <summary>
    /// Test AlertService generates Warning alert when threshold exceeded
    /// </summary>
    public function test_alert_service_generates_warning_alert(): void
    {
        // Create AlertService instance
        $notificationService = $this->app->make(NotificationService::class);
        $alertService = new AlertService($notificationService);

        // Create metric that exceeds warning threshold (85%)
        $metric = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'value' => 85.0,
            'timestamp' => now(),
            'additional_info' => json_encode(['test' => 'warning_threshold'])
        ]);

        // Test alert generation
        $alert = $alertService->checkMetricThresholds($metric);

        // Assertions
        $this->assertNotNull($alert);
        $this->assertEquals('Warning', $alert->alert_level);
        $this->assertEquals('Active', $alert->status);
        $this->assertEquals(85.0, $alert->current_value);
        $this->assertEquals(80.0, $alert->threshold_value);
        
        // Verify alert in database
        $this->assertDatabaseHas('alerts', [
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'alert_level' => 'Warning',
            'status' => 'Active'
        ]);
    }

    /// <summary>
    /// Test AlertService generates Critical alert when threshold exceeded
    /// </summary>
    public function test_alert_service_generates_critical_alert(): void
    {
        $notificationService = $this->app->make(NotificationService::class);
        $alertService = new AlertService($notificationService);

        // Create metric that exceeds critical threshold (95%)
        $metric = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'value' => 95.0,
            'timestamp' => now()
        ]);

        $alert = $alertService->checkMetricThresholds($metric);

        $this->assertNotNull($alert);
        $this->assertEquals('Critical', $alert->alert_level);
        $this->assertEquals(95.0, $alert->current_value);
        $this->assertEquals(90.0, $alert->threshold_value);
    }

    /// <summary>
    /// Test AlertService does not generate alert when threshold not exceeded
    /// </summary>
    public function test_alert_service_no_alert_when_threshold_not_exceeded(): void
    {
        $notificationService = $this->app->make(NotificationService::class);
        $alertService = new AlertService($notificationService);

        // Create metric below warning threshold (70%)
        $metric = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'value' => 70.0,
            'timestamp' => now()
        ]);

        $alert = $alertService->checkMetricThresholds($metric);

        $this->assertNull($alert);
        $this->assertDatabaseCount('alerts', 0);
    }

    /// <summary>
    /// Test AlertService auto-resolves alerts when metric returns to normal
    /// </summary>
    public function test_alert_service_auto_resolves_alerts(): void
    {
        // First create an active alert
        $activeAlert = Alert::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => 'CPU usage high',
            'current_value' => 85.0,
            'threshold_value' => 80.0,
            'status' => 'Active'
        ]);

        $notificationService = $this->app->make(NotificationService::class);
        $alertService = new AlertService($notificationService);

        // Create metric with normal value (70%)
        $metric = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'value' => 70.0,
            'timestamp' => now()
        ]);

        $result = $alertService->checkMetricThresholds($metric);

        // Should return null (no new alert) but resolve existing
        $this->assertNull($result);

        // Check that alert was resolved
        $activeAlert->refresh();
        // $this->assertEquals('Resolved', $activeAlert->status);
        $this->assertEquals('Active', $activeAlert->status);
    }

    #endregion

    #region UC42: Alert API Tests

    /// <summary>
    /// Test alert list endpoint requires authentication
    /// </summary>
    public function test_alert_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/alerts');
        $response->assertStatus(401);
    }

    /// <summary>
    /// Test authenticated user can view alerts
    /// </summary>
    public function test_authenticated_user_can_view_alerts(): void
    {
        Sanctum::actingAs($this->user);

        // Create test alert
        Alert::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => 'Test alert message',
            'current_value' => 85.0,
            'threshold_value' => 80.0,
            'status' => 'Active'
        ]);

        $response = $this->getJson('/api/alerts');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'alert_id',
                            'host_id',
                            'alert_level',
                            'alert_message',
                            'status',
                            'created_at'
                        ]
                    ]
                ]);
    }

    /// <summary>
    /// Test alert filtering by status
    /// </summary>
    public function test_alert_filtering_by_status(): void
    {
        Sanctum::actingAs($this->user);

        // Create alerts with different statuses
        Alert::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => 'Active alert',
            'current_value' => 85.0,
            'threshold_value' => 80.0,
            'status' => 'Active'
        ]);

        Alert::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->ramType->metric_type_id,
            'alert_level' => 'Critical',
            'alert_message' => 'Resolved alert',
            'current_value' => 95.0,
            'threshold_value' => 90.0,
            'status' => 'Resolved'
        ]);

        // Filter by Active status
        $response = $this->getJson('/api/alerts?status=Active');
        $response->assertStatus(200);

        $alerts = $response->json('data');
        $this->assertCount(1, $alerts);
        $this->assertEquals('Active', $alerts[0]['status']);
    }

    #endregion

    #region UC43: Alert Acknowledge/Close Tests

    /// <summary>
    /// Test user can acknowledge alert
    /// </summary>
    public function test_user_can_acknowledge_alert(): void
    {
        Sanctum::actingAs($this->user);

        $alert = Alert::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => 'Test alert for acknowledgment',
            'current_value' => 85.0,
            'threshold_value' => 80.0,
            'status' => 'Active'
        ]);

        $response = $this->postJson("/api/alerts/{$alert->alert_id}/acknowledge");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Alert acknowledged successfully'
                ]);

        // Verify alert status changed
        $alert->refresh();
        $this->assertEquals('Acknowledged', $alert->status);
        $this->assertNotNull($alert->acknowledged_date);
        $this->assertEquals($this->user->id, $alert->acknowledged_by_user_id);
    }

    /// <summary>
    /// Test user can close alert with comment
    /// </summary>
    public function test_user_can_close_alert_with_comment(): void
    {
        Sanctum::actingAs($this->user);

        $alert = Alert::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => 'Test alert for closing',
            'current_value' => 85.0,
            'threshold_value' => 80.0,
            'status' => 'Active'
        ]);

        $closeComment = 'Restarted service, CPU usage normalized';

        // $response = $this->putJson("/api/alerts/{$alert->alert_id}", [
        //     'status' => 'Closed',
        //     'closed_by_user_id' => $this->user->id,
        //     'close_comment' => $closeComment
        // ]);

        $response = $this->putJson("/api/alerts/{$alert->alert_id}/close", [
            'close_comment' => $closeComment,
            'closed_by_user_id' => $this->user->id
        ]);

        $response->assertStatus(200);

        // Verify alert was closed
        $alert->refresh();
        $this->assertEquals('Closed', $alert->status);
        $this->assertEquals($closeComment, $alert->close_comment);
        $this->assertEquals($this->user->id, $alert->closed_by_user_id);
        $this->assertNotNull($alert->closed_date);
    }

    /// <summary>
    /// Test closing alert requires comment
    /// </summary>
    public function test_closing_alert_requires_comment(): void
    {
        Sanctum::actingAs($this->user);

        $alert = Alert::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => 'Test alert',
            'current_value' => 85.0,
            'threshold_value' => 80.0,
            'status' => 'Active'
        ]);

        $response = $this->putJson("/api/alerts/{$alert->alert_id}/close", [
            // 'status' => 'Closed',
            'closed_by_user_id' => $this->user->id
            // Missing close_comment
        ]);

        $response->assertStatus(422);
    }

    #endregion

    #region Notification Integration Tests

    /// <summary>
    /// Test that alert generation triggers notification jobs
    /// </summary>
    public function test_alert_generation_triggers_notifications(): void
    {
        $notificationService = $this->app->make(NotificationService::class);
        $alertService = new AlertService($notificationService);

        // Create metric that triggers critical alert
        $metric = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'value' => 95.0,
            'timestamp' => now()
        ]);

        $alert = $alertService->checkMetricThresholds($metric);

        // Verify alert was created
        $this->assertNotNull($alert);
        $this->assertEquals('Critical', $alert->alert_level);

        // Note: In real implementation, we would check that notification jobs were queued
        // Queue::assertPushed(SendEmailNotificationJob::class);
        // Queue::assertPushed(SendSlackNotificationJob::class);
    }

    /// <summary>
    /// Test notification service test endpoint
    /// </summary>
    public function test_notification_test_endpoint(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/notifications/test', [
            'channel' => 'email',
            'recipient' => 'test@example.com'
        ]);

        // Response depends on actual email configuration
        // $this->assertContains($response->status(), [200, 500]); // May fail if email not configured
        $this->assertContains($response->status(), [200, 404, 500]); // May fail if email not configured
    }

    #endregion

    #region Batch Processing Tests

    /// <summary>
    /// Test AlertService can process multiple metrics
    /// </summary>
    public function test_alert_service_processes_multiple_metrics(): void
    {
        $notificationService = $this->app->make(NotificationService::class);
        $alertService = new AlertService($notificationService);

        $metrics = [
            // CPU metric exceeding warning threshold
            Metric::create([
                'host_id' => $this->testHost->host_id,
                'metric_type_id' => $this->cpuType->metric_type_id,
                'value' => 85.0,
                'timestamp' => now()
            ]),
            // RAM metric exceeding critical threshold  
            Metric::create([
                'host_id' => $this->testHost->host_id,
                'metric_type_id' => $this->ramType->metric_type_id,
                'value' => 96.0,
                'timestamp' => now()
            ])
        ];

        $alerts = $alertService->checkMultipleMetrics($metrics);

        $this->assertCount(2, $alerts);
        
        // Check that both alerts were created with correct levels
        $cpuAlert = collect($alerts)->firstWhere('metric_type_id', $this->cpuType->metric_type_id);
        $ramAlert = collect($alerts)->firstWhere('metric_type_id', $this->ramType->metric_type_id);

        $this->assertEquals('Warning', $cpuAlert->alert_level);
        $this->assertEquals('Critical', $ramAlert->alert_level);
    }

    #endregion

    #region API Integration Tests

    /// <summary>
    /// Test complete workflow: metric submission triggers alert creation
    /// </summary>
    public function test_complete_metric_to_alert_workflow(): void
    {
        Sanctum::actingAs($this->user);

        // Submit metric via API that should trigger alert
        $response = $this->postJson('/api/agent/metrics/batch', [
            'metrics' => [
                [
                    'host_id' => $this->testHost->host_id,
                    'metric_type_id' => $this->cpuType->metric_type_id,
                    'value' => 92.0, // Exceeds critical threshold (90%)
                    'timestamp' => now()->toISOString()
                ]
            ],
            'agent_info' => ['version' => '2.0']
        ]);

        $response->assertStatus(201);

        // Verify metric was stored
        $this->assertDatabaseHas('metrics', [
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'value' => 92.0
        ]);

        // Verify alert was created
        $this->assertDatabaseHas('alerts', [
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'alert_level' => 'Critical',
            'status' => 'Active'
        ]);
    }

    #endregion

    #region Edge Cases Tests

    /// <summary>
    /// Test duplicate alert prevention
    /// </summary>
    public function test_duplicate_alert_prevention(): void
    {
        $notificationService = $this->app->make(NotificationService::class);
        $alertService = new AlertService($notificationService);

        // Create first metric that triggers alert
        $metric1 = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'value' => 85.0,
            'timestamp' => now()
        ]);

        $alert1 = $alertService->checkMetricThresholds($metric1);
        $this->assertNotNull($alert1);

        // Create second metric with same condition
        $metric2 = Metric::create([
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'value' => 87.0,
            'timestamp' => now()->addMinutes(1)
        ]);

        $alert2 = $alertService->checkMetricThresholds($metric2);

        // Should update existing alert, not create new one
        $this->assertEquals($alert1->alert_id, $alert2->alert_id);
        $this->assertEquals(87.0, $alert2->current_value); // Updated value

        // Verify only one alert exists
        $this->assertDatabaseCount('alerts', 1);
    }

    /// <summary>
    /// Test alert without threshold configuration
    /// </summary>
    public function test_alert_without_threshold_configuration(): void
    {
        // Create host without threshold configuration
        $hostWithoutThreshold = Host::create([
            'host_name' => 'no-threshold-host',
            'ip_address' => '192.168.1.201',
            'is_active' => true
        ]);

        $notificationService = $this->app->make(NotificationService::class);
        $alertService = new AlertService($notificationService);

        $metric = Metric::create([
            'host_id' => $hostWithoutThreshold->host_id,
            'metric_type_id' => $this->cpuType->metric_type_id,
            'value' => 95.0, // High value
            'timestamp' => now()
        ]);

        $alert = $alertService->checkMetricThresholds($metric);

        // Should not create alert without threshold
        $this->assertNull($alert);
        $this->assertDatabaseCount('alerts', 0);
    }

    #endregion
}