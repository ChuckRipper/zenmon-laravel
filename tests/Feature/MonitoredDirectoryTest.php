<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Host, MonitoredDirectory, DirectoryMetric};

class MonitoredDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;
    private Host $testHost;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = $this->createTestUser('Administrator');
        $this->normalUser = $this->createTestUser('User');
        $this->testHost = $this->createTestHost();
    }

    #region Directory Management Tests

    /// <summary>
    /// Test admin can add monitored directory
    /// </summary>
    public function test_admin_can_add_monitored_directory(): void
    {
        Sanctum::actingAs($this->adminUser);

        $directoryData = [
            'host_id' => $this->testHost->host_id,
            'directory_path' => '/var/www/html',
            'description' => 'Web server document root',
            'is_active' => true
        ];

        $response = $this->postJson('/api/monitored-directories', $directoryData);

        $response->assertStatus(201)
                ->assertJsonPath('data.directory_path', '/var/www/html');

        $this->assertDatabaseHas('monitored_directories', [
            'host_id' => $this->testHost->host_id,
            'directory_path' => '/var/www/html'
        ]);
    }

    /// <summary>
    /// Test user cannot add monitored directories
    /// </summary>
    public function test_user_cannot_add_monitored_directory(): void
    {
        Sanctum::actingAs($this->normalUser);

        $response = $this->postJson('/api/monitored-directories', [
            'host_id' => $this->testHost->host_id,
            'directory_path' => '/unauthorized'
        ]);

        $response->assertStatus(403);
    }

    /// <summary>
    /// Test duplicate directory path validation
    /// </summary>
    public function test_duplicate_directory_path_validation(): void
    {
        Sanctum::actingAs($this->adminUser);

        $existingDirectory = $this->createTestMonitoredDirectory($this->testHost);

        $response = $this->postJson('/api/monitored-directories', [
            'host_id' => $this->testHost->host_id,
            'directory_path' => $existingDirectory->directory_path // Same path
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['directory_path']);
    }

    #endregion

    #region Directory Metrics Tests

    /// <summary>
    /// Test agent can submit directory metrics
    /// </summary>
    public function test_agent_can_submit_directory_metrics(): void
    {
        Sanctum::actingAs($this->normalUser); // Agent uses normal user auth

        $directory = $this->createTestMonitoredDirectory($this->testHost);

        $metricsData = [
            'directory_metrics' => [
                [
                    'directory_id' => $directory->directory_id,
                    'used_space' => 1073741824, // 1GB
                    'total_space' => 10737418240, // 10GB
                    'available_space' => 9663676416, // 9GB
                    'file_count' => 150,
                    'timestamp' => now()->toISOString()
                ]
            ],
            'agent_info' => [
                'version' => '2.0',
                'platform' => 'Linux'
            ]
        ];

        $response = $this->postJson('/api/agent/directory-metrics', $metricsData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('directory_metrics', [
            'directory_id' => $directory->directory_id,
            'used_space' => 1073741824,
            'file_count' => 150
        ]);
    }

    /// <summary>
    /// Test directory metrics require authentication
    /// </summary>
    public function test_directory_metrics_require_authentication(): void
    {
        $response = $this->postJson('/api/agent/directory-metrics', [
            'directory_metrics' => []
        ]);

        $response->assertStatus(401);
    }

    /// <summary>
    /// Test users can view directory metrics
    /// </summary>
    public function test_users_can_view_directory_metrics(): void
    {
        Sanctum::actingAs($this->normalUser);

        $directory = $this->createTestMonitoredDirectory($this->testHost);
        
        DirectoryMetric::create([
            'directory_id' => $directory->directory_id,
            'used_space' => 2147483648, // 2GB
            'total_space' => 10737418240, // 10GB
            'available_space' => 8589934592, // 8GB
            'file_count' => 200,
            'timestamp' => now()
        ]);

        $response = $this->getJson('/api/directory-metrics');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(2147483648, $data[0]['used_space']);
    }

    #endregion
}