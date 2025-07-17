<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Host, MetricType, AlertThreshold, MonitoredDirectory};

abstract class TestCase extends BaseTestCase
{
    #region Helper Methods

    /// <summary>
    /// Create test user with specified role
    /// </summary>
    /// <param>string $role</param>
    /// <param>array $attributes</param>
    /// <returns>User</returns>
    protected function createTestUser(string $role = 'User', array $attributes = []): User
    {
        return User::create(array_merge([
            'login' => 'test_' . strtolower($role) . '_' . rand(1000, 9999),
            'password' => Hash::make('testpass123'),
            'email' => 'test' . rand(1000, 9999) . '@test.local',
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
            'role' => $role,
            'is_active' => true
        ], $attributes));
    }

    /// <summary>
    /// Create test host with default values
    /// </summary>
    /// <param>array $attributes</param>
    /// <returns>Host</returns>
    protected function createTestHost(array $attributes = []): Host
    {
        return Host::create(array_merge([
            'host_name' => 'test-host-' . rand(1000, 9999),
            'ip_address' => '192.168.1.' . rand(100, 199),
            'description' => 'Test host for automated testing',
            'operating_system' => 'Ubuntu 22.04 LTS',
            'agent_version' => '2.0',
            'is_active' => true
        ], $attributes));
    }

    /// <summary>
    /// Create test metric types (CPU, RAM, Disk, Network)
    /// </summary>
    /// <returns>array</returns>
    protected function createTestMetricTypes(): array
    {
        return [
            'cpu' => MetricType::create([
                'metric_name' => 'CPU',
                'unit' => '%',
                'description' => 'CPU Usage'
            ]),
            'ram' => MetricType::create([
                'metric_name' => 'RAM',
                'unit' => '%',
                'description' => 'Memory Usage'
            ]),
            'disk' => MetricType::create([
                'metric_name' => 'Disk',
                'unit' => '%',
                'description' => 'Disk Usage'
            ]),
            'network' => MetricType::create([
                'metric_name' => 'Network',
                'unit' => 'ms',
                'description' => 'Network Response Time'
            ])
        ];
    }

    /// <summary>
    /// Create test alert thresholds for host and metric type
    /// </summary>
    /// <param>Host $host</param>
    /// <param>MetricType $metricType</param>
    /// <param>User $user</param>
    /// <returns>AlertThreshold</returns>
    protected function createTestAlertThreshold(Host $host, MetricType $metricType, User $user): AlertThreshold
    {
        return AlertThreshold::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricType->metric_type_id,
            'warning_threshold' => 80.0,
            'critical_threshold' => 90.0,
            'is_active' => true,
            'created_by_user_id' => $user->user_id
        ]);
    }

    /// <summary>
    /// Create test monitored directory
    /// </summary>
    /// <param>Host $host</param>
    /// <returns>MonitoredDirectory</returns>
    protected function createTestMonitoredDirectory(Host $host): MonitoredDirectory
    {
        return MonitoredDirectory::create([
            'host_id' => $host->host_id,
            'directory_path' => '/var/log',
            'description' => 'System logs directory',
            'is_active' => true
        ]);
    }

    /// <summary>
    /// Authenticate user for API tests
    /// </summary>
    /// <param>User $user</param>
    /// <returns>void</returns>
    protected function authenticateAs(User $user): void
    {
        Sanctum::actingAs($user);
    }

    /// <summary>
    /// Assert JSON response has paginated structure
    /// </summary>
    /// <param>mixed $response</param>
    /// <returns>void</returns>
    protected function assertPaginatedResponse($response): void
    {
        $response->assertJsonStructure([
            'data' => ['*' => []],
            'links' => [
                'first',
                'last',
                'prev',
                'next'
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'path',
                'per_page',
                'to',
                'total'
            ]
        ]);
    }

    #endregion
}