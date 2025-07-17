<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Host, Metric, MetricType, Alert, AlertThreshold};
use App\Models\{HostConfiguration, MonitoredDirectory, DirectoryMetric};
use App\Models\{ConnectionStatus, UserSession};
use Illuminate\Support\Facades\Hash;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    #region User Model Tests

    /// <summary>
    /// Test User model basic attributes and relationships
    /// </summary>
    public function test_user_model_attributes_and_relationships(): void
    {
        $user = $this->createTestUser('Administrator');

        // Test attributes
        $this->assertNotEmpty($user->login);
        $this->assertNotEmpty($user->email);
        $this->assertNotEmpty($user->first_name);
        $this->assertNotEmpty($user->last_name);
        $this->assertEquals('Administrator', $user->role);
        $this->assertTrue($user->is_active);

        // Test password hashing
        $this->assertTrue(Hash::check('testpass123', $user->password));

        // Test full name accessor
        $expected = $user->first_name . ' ' . $user->last_name;
        $this->assertEquals($expected, $user->full_name);
    }

    /// <summary>
    /// Test User relationships work correctly
    /// </summary>
    public function test_user_model_relationships(): void
    {
        $user = $this->createTestUser('Administrator');
        $host = $this->createTestHost();
        $metricTypes = $this->createTestMetricTypes();

        // Create related data
        $threshold = $this->createTestAlertThreshold($host, $metricTypes['cpu'], $user);
        
        $alert = Alert::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricTypes['cpu']->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => 'Test alert',
            'current_value' => 85.0,
            'threshold_value' => 80.0,
            'acknowledged_by_user_id' => $user->user_id,
            'status' => 'Acknowledged'
        ]);

        // Test relationships
        $this->assertInstanceOf(AlertThreshold::class, $user->createdAlertThresholds->first());
        $this->assertInstanceOf(Alert::class, $user->acknowledgedAlerts->first());
        $this->assertEquals($alert->alert_id, $user->acknowledgedAlerts->first()->alert_id);
    }

    #endregion

    #region Host Model Tests

    /// <summary>
    /// Test Host model attributes and basic functionality
    /// </summary>
    public function test_host_model_attributes(): void
    {
        $host = $this->createTestHost([
            'host_name' => 'production-server-01',
            'ip_address' => '10.0.1.100',
            'operating_system' => 'Ubuntu 22.04 LTS'
        ]);

        $this->assertEquals('production-server-01', $host->host_name);
        $this->assertEquals('10.0.1.100', $host->ip_address);
        $this->assertEquals('Ubuntu 22.04 LTS', $host->operating_system);
        $this->assertTrue($host->is_active);
    }

    /// <summary>
    /// Test Host model relationships
    /// </summary>
    public function test_host_model_relationships(): void
    {
        $host = $this->createTestHost();
        $metricTypes = $this->createTestMetricTypes();

        // Create metrics for host
        $metric = Metric::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricTypes['cpu']->metric_type_id,
            'value' => 45.5,
            'timestamp' => now()
        ]);

        // Create monitored directory
        $directory = $this->createTestMonitoredDirectory($host);

        // Create host configuration
        $user = $this->createTestUser();
        $config = HostConfiguration::create([
            'host_id' => $host->host_id,
            'data_collection_interval' => 120,
            'enable_cpu_monitoring' => true,
            'enable_ram_monitoring' => true,
            'enable_disk_monitoring' => true,
            'enable_network_monitoring' => true,
            'updated_by_user_id' => $user->user_id
        ]);

        // Test relationships
        $this->assertInstanceOf(Metric::class, $host->metrics->first());
        $this->assertInstanceOf(MonitoredDirectory::class, $host->monitoredDirectories->first());
        $this->assertInstanceOf(HostConfiguration::class, $host->configuration);
        $this->assertEquals(120, $host->configuration->data_collection_interval);
    }

    #endregion

    #region Metric Model Tests

    /// <summary>
    /// Test Metric model attributes and relationships
    /// </summary>
    public function test_metric_model_attributes_and_relationships(): void
    {
        $host = $this->createTestHost();
        $metricTypes = $this->createTestMetricTypes();

        $metric = Metric::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricTypes['cpu']->metric_type_id,
            'value' => 67.8,
            'timestamp' => now(),
            'additional_info' => ['cores' => 4, 'load_avg' => 1.5]
        ]);

        // Test attributes
        $this->assertEquals($host->host_id, $metric->host_id);
        $this->assertEquals(67.8, $metric->value);
        $this->assertIsArray($metric->additional_info);
        $this->assertEquals(4, $metric->additional_info['cores']);

        // Test relationships
        $this->assertInstanceOf(Host::class, $metric->host);
        $this->assertInstanceOf(MetricType::class, $metric->metricType);
        $this->assertEquals('CPU', $metric->metricType->metric_name);
        $this->assertEquals('%', $metric->metricType->unit);
    }

    #endregion

    #region MetricType Model Tests

    /// <summary>
    /// Test MetricType model basic functionality
    /// </summary>
    public function test_metric_type_model_attributes(): void
    {
        $metricType = MetricType::create([
            'metric_name' => 'Custom Storage',
            'unit' => 'GB',
            'description' => 'Custom storage monitoring metric'
        ]);

        $this->assertEquals('Custom Storage', $metricType->metric_name);
        $this->assertEquals('GB', $metricType->unit);
        $this->assertEquals('Custom storage monitoring metric', $metricType->description);
    }

    /// <summary>
    /// Test MetricType relationships
    /// </summary>
    public function test_metric_type_relationships(): void
    {
        $host = $this->createTestHost();
        $metricType = MetricType::create([
            'metric_name' => 'Test Metric',
            'unit' => '%',
            'description' => 'Test metric for relationships'
        ]);

        // Create metric using this type
        $metric = Metric::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricType->metric_type_id,
            'value' => 50.0,
            'timestamp' => now()
        ]);

        // Create alert threshold
        $user = $this->createTestUser();
        $threshold = AlertThreshold::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricType->metric_type_id,
            'warning_threshold' => 75.0,
            'critical_threshold' => 90.0,
            'created_by_user_id' => $user->user_id
        ]);

        // Test relationships
        $this->assertInstanceOf(Metric::class, $metricType->metrics->first());
        $this->assertInstanceOf(AlertThreshold::class, $metricType->alertThresholds->first());
        $this->assertEquals(75.0, $metricType->alertThresholds->first()->warning_threshold);
    }

    #endregion

    #region Alert Model Tests

    /// <summary>
    /// Test Alert model attributes and lifecycle
    /// </summary>
    public function test_alert_model_attributes_and_lifecycle(): void
    {
        $host = $this->createTestHost();
        $metricTypes = $this->createTestMetricTypes();
        $user = $this->createTestUser();

        $alert = Alert::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricTypes['cpu']->metric_type_id,
            'alert_level' => 'Critical',
            'alert_message' => 'CPU usage critically high',
            'current_value' => 95.5,
            'threshold_value' => 90.0,
            'status' => 'Active'
        ]);

        // Test initial attributes
        $this->assertEquals('Critical', $alert->alert_level);
        $this->assertEquals('Active', $alert->status);
        $this->assertEquals(95.5, $alert->current_value);
        $this->assertEquals(90.0, $alert->threshold_value);

        // Test acknowledging alert
        $alert->update([
            'status' => 'Acknowledged',
            'acknowledged_date' => now(),
            'acknowledged_by_user_id' => $user->user_id
        ]);

        $this->assertEquals('Acknowledged', $alert->status);
        $this->assertNotNull($alert->acknowledged_date);
        $this->assertEquals($user->user_id, $alert->acknowledged_by_user_id);

        // Test closing alert
        $alert->update([
            'status' => 'Closed',
            'closed_date' => now(),
            'closed_by_user_id' => $user->user_id,
            'close_comment' => 'Issue resolved by restarting service'
        ]);

        $this->assertEquals('Closed', $alert->status);
        $this->assertNotNull($alert->closed_date);
        $this->assertEquals('Issue resolved by restarting service', $alert->close_comment);
    }

    /// <summary>
    /// Test Alert model relationships
    /// </summary>
    public function test_alert_model_relationships(): void
    {
        $host = $this->createTestHost();
        $metricTypes = $this->createTestMetricTypes();
        $user = $this->createTestUser();

        $alert = Alert::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricTypes['ram']->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => 'Memory usage high',
            'current_value' => 85.0,
            'threshold_value' => 80.0,
            'status' => 'Acknowledged',
            'acknowledged_by_user_id' => $user->user_id
        ]);

        // Test relationships
        $this->assertInstanceOf(Host::class, $alert->host);
        $this->assertInstanceOf(MetricType::class, $alert->metricType);
        $this->assertInstanceOf(User::class, $alert->acknowledgedByUser);
        $this->assertEquals($host->host_name, $alert->host->host_name);
        $this->assertEquals('RAM', $alert->metricType->metric_name);
        $this->assertEquals($user->login, $alert->acknowledgedByUser->login);
    }

    #endregion

    #region AlertThreshold Model Tests

    /// <summary>
    /// Test AlertThreshold model functionality
    /// </summary>
    public function test_alert_threshold_model(): void
    {
        $host = $this->createTestHost();
        $metricTypes = $this->createTestMetricTypes();
        $user = $this->createTestUser();

        $threshold = AlertThreshold::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricTypes['disk']->metric_type_id,
            'warning_threshold' => 85.0,
            'critical_threshold' => 95.0,
            'is_active' => true,
            'created_by_user_id' => $user->user_id
        ]);

        // Test attributes
        $this->assertEquals(85.0, $threshold->warning_threshold);
        $this->assertEquals(95.0, $threshold->critical_threshold);
        $this->assertTrue($threshold->is_active);

        // Test relationships
        $this->assertInstanceOf(Host::class, $threshold->host);
        $this->assertInstanceOf(MetricType::class, $threshold->metricType);
        $this->assertInstanceOf(User::class, $threshold->createdByUser);
        $this->assertEquals('Disk', $threshold->metricType->metric_name);
    }

    /// <summary>
    /// Test global alert threshold (no host_id)
    /// </summary>
    public function test_global_alert_threshold(): void
    {
        $metricTypes = $this->createTestMetricTypes();
        $user = $this->createTestUser();

        $globalThreshold = AlertThreshold::create([
            'host_id' => null, // Global threshold
            'metric_type_id' => $metricTypes['network']->metric_type_id,
            'warning_threshold' => 100.0,
            'critical_threshold' => 200.0,
            'is_active' => true,
            'created_by_user_id' => $user->user_id
        ]);

        $this->assertNull($globalThreshold->host_id);
        $this->assertNull($globalThreshold->host);
        $this->assertEquals('Network', $globalThreshold->metricType->metric_name);
    }

    #endregion

    #region HostConfiguration Model Tests

    /// <summary>
    /// Test HostConfiguration model 1:1 relationship with Host
    /// </summary>
    public function test_host_configuration_model(): void
    {
        $host = $this->createTestHost();
        $user = $this->createTestUser();

        $config = HostConfiguration::create([
            'host_id' => $host->host_id,
            'data_collection_interval' => 180,
            'enable_cpu_monitoring' => true,
            'enable_ram_monitoring' => true,
            'enable_disk_monitoring' => false,
            'enable_network_monitoring' => true,
            'updated_by_user_id' => $user->user_id
        ]);

        // Test attributes
        $this->assertEquals(180, $config->data_collection_interval);
        $this->assertTrue($config->enable_cpu_monitoring);
        $this->assertFalse($config->enable_disk_monitoring);

        // Test relationships
        $this->assertInstanceOf(Host::class, $config->host);
        $this->assertInstanceOf(User::class, $config->updatedByUser);
        $this->assertEquals($host->host_id, $config->host->host_id);

        // Test unique constraint (1:1 relationship)
        $this->expectException(\Illuminate\Database\QueryException::class);
        HostConfiguration::create([
            'host_id' => $host->host_id, // Same host_id should fail
            'data_collection_interval' => 120,
            'updated_by_user_id' => $user->user_id
        ]);
    }

    #endregion

    #region MonitoredDirectory Model Tests

    /// <summary>
    /// Test MonitoredDirectory model and its metrics
    /// </summary>
    public function test_monitored_directory_model(): void
    {
        $host = $this->createTestHost();
        
        $directory = MonitoredDirectory::create([
            'host_id' => $host->host_id,
            'directory_path' => '/var/www/html',
            'description' => 'Web server root directory',
            'is_active' => true
        ]);

        // Create directory metric
        $directoryMetric = DirectoryMetric::create([
            'directory_id' => $directory->directory_id,
            'used_space' => 5368709120, // 5GB in bytes
            'total_space' => 10737418240, // 10GB in bytes
            'available_space' => 5368709120, // 5GB in bytes
            'file_count' => 1250,
            'timestamp' => now()
        ]);

        // Test attributes
        $this->assertEquals('/var/www/html', $directory->directory_path);
        $this->assertTrue($directory->is_active);

        // Test relationships
        $this->assertInstanceOf(Host::class, $directory->host);
        $this->assertInstanceOf(DirectoryMetric::class, $directory->directoryMetrics->first());
        $this->assertEquals(1250, $directory->directoryMetrics->first()->file_count);
    }

    #endregion

    #region ConnectionStatus Model Tests

    /// <summary>
    /// Test ConnectionStatus model for tracking host connectivity
    /// </summary>
    public function test_connection_status_model(): void
    {
        $host = $this->createTestHost();

        $status = ConnectionStatus::create([
            'host_id' => $host->host_id,
            'status' => 'Online',
            'response_time' => 25,
            'last_check_date' => now(),
            'error_message' => null
        ]);

        // Test attributes
        $this->assertEquals('Online', $status->status);
        $this->assertEquals(25, $status->response_time);
        $this->assertNull($status->error_message);

        // Test relationship
        $this->assertInstanceOf(Host::class, $status->host);
        $this->assertEquals($host->host_name, $status->host->host_name);

        // Test offline status with error
        $offlineStatus = ConnectionStatus::create([
            'host_id' => $host->host_id,
            'status' => 'Offline',
            'response_time' => null,
            'last_check_date' => now(),
            'error_message' => 'Connection timeout after 30 seconds'
        ]);

        $this->assertEquals('Offline', $offlineStatus->status);
        $this->assertNotNull($offlineStatus->error_message);
    }

    #endregion

    #region UserSession Model Tests

    /// <summary>
    /// Test UserSession model for tracking user sessions
    /// </summary>
    public function test_user_session_model(): void
    {
        $user = $this->createTestUser();

        $session = UserSession::create([
            'user_id' => $user->user_id,
            'session_token' => 'test_token_' . rand(1000000, 9999999),
            'login_date' => now(),
            'last_activity_date' => now(),
            'ip_address' => '192.168.1.100',
            'is_active' => true
        ]);

        // Test attributes
        $this->assertTrue($session->is_active);
        $this->assertEquals('192.168.1.100', $session->ip_address);
        $this->assertNotNull($session->session_token);

        // Test relationship
        $this->assertInstanceOf(User::class, $session->user);
        $this->assertEquals($user->login, $session->user->login);

        // Test unique session token constraint
        $this->expectException(\Illuminate\Database\QueryException::class);
        UserSession::create([
            'user_id' => $user->user_id,
            'session_token' => $session->session_token, // Same token should fail
            'ip_address' => '192.168.1.101'
        ]);
    }

    #endregion

    #region Model Factory Tests

    /// <summary>
    /// Test that model factories work correctly
    /// </summary>
    public function test_model_factories(): void
    {
        // Test User factory
        $user = \App\Models\User::factory()->create();
        $this->assertNotNull($user->login);
        $this->assertNotNull($user->email);
        $this->assertTrue(Hash::check($user->login . '123', $user->password));

        // Test multiple users
        $users = \App\Models\User::factory()->count(3)->create();
        $this->assertCount(3, $users);
        
        // Test each user has unique login and email
        $logins = $users->pluck('login')->toArray();
        $emails = $users->pluck('email')->toArray();
        $this->assertEquals(3, count(array_unique($logins)));
        $this->assertEquals(3, count(array_unique($emails)));
    }

    #endregion

    #region Database Integrity Tests

    /// <summary>
    /// Test foreign key constraints work correctly
    /// </summary>
    public function test_foreign_key_constraints(): void
    {
        $host = $this->createTestHost();
        $metricTypes = $this->createTestMetricTypes();

        // Create metric
        $metric = Metric::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricTypes['cpu']->metric_type_id,
            'value' => 50.0,
            'timestamp' => now()
        ]);

        // Delete host should cascade delete metric
        $metricId = $metric->metric_id;
        $host->delete();
        
        $this->assertDatabaseMissing('metrics', ['metric_id' => $metricId]);

        // But metric type should still exist (RESTRICT)
        $this->assertDatabaseHas('metric_types', [
            'metric_type_id' => $metricTypes['cpu']->metric_type_id
        ]);
    }

    /// <summary>
    /// Test unique constraints work correctly
    /// </summary>
    public function test_unique_constraints(): void
    {
        $user1 = $this->createTestUser();
        
        // Try to create user with same login
        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\User::create([
            'login' => $user1->login, // Same login should fail
            'password' => Hash::make('different_password'),
            'email' => 'different@email.com',
            'first_name' => 'Different',
            'last_name' => 'User',
            'role' => 'User'
        ]);
    }

    #endregion
}