<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Host, HostConfiguration};

class HostApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = $this->createTestUser('Administrator');
        $this->normalUser = $this->createTestUser('User');
    }

    #region UC20: Add Host Tests

    /// <summary>
    /// Test administrator can add new host
    /// </summary>
    public function test_admin_can_add_new_host(): void
    {
        Sanctum::actingAs($this->adminUser);

        $hostData = [
            'host_name' => 'production-web-01',
            'ip_address' => '10.0.1.50',
            'description' => 'Production web server',
            'operating_system' => 'Ubuntu 22.04 LTS'
        ];

        $response = $this->postJson('/api/hosts', $hostData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'host_id',
                        'host_name',
                        'ip_address',
                        'description',
                        'operating_system',
                        'is_active'
                    ]
                ]);

        $this->assertDatabaseHas('hosts', [
            'host_name' => 'production-web-01',
            'ip_address' => '10.0.1.50'
        ]);
    }

    /// <summary>
    /// Test regular user cannot add hosts
    /// </summary>
    public function test_user_cannot_add_host(): void
    {
        Sanctum::actingAs($this->normalUser);

        $hostData = [
            'host_name' => 'unauthorized-host',
            'ip_address' => '10.0.1.99'
        ];

        $response = $this->postJson('/api/hosts', $hostData);
        $response->assertStatus(403);
    }

    /// <summary>
    /// Test duplicate IP address validation
    /// </summary>
    public function test_duplicate_ip_address_validation(): void
    {
        Sanctum::actingAs($this->adminUser);

        $existingHost = $this->createTestHost(['ip_address' => '10.0.1.100']);

        $response = $this->postJson('/api/hosts', [
            'host_name' => 'duplicate-ip-host',
            'ip_address' => '10.0.1.100' // Same IP
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['ip_address']);
    }

    #endregion

    #region UC21: Delete Host Tests

    /// <summary>
    /// Test administrator can delete host
    /// </summary>
    public function test_admin_can_delete_host(): void
    {
        Sanctum::actingAs($this->adminUser);

        $host = $this->createTestHost();

        $response = $this->deleteJson("/api/hosts/{$host->host_id}");

        $response->assertStatus(200)
                ->assertJson(['message' => 'Host deleted successfully']);

        $this->assertDatabaseMissing('hosts', ['host_id' => $host->host_id]);
    }

    /// <summary>
    /// Test regular user cannot delete hosts
    /// </summary>
    public function test_user_cannot_delete_host(): void
    {
        Sanctum::actingAs($this->normalUser);

        $host = $this->createTestHost();

        $response = $this->deleteJson("/api/hosts/{$host->host_id}");
        $response->assertStatus(403);
    }

    #endregion

    #region UC22: List Hosts Tests

    /// <summary>
    /// Test users can view hosts list
    /// </summary>
    public function test_users_can_view_hosts_list(): void
    {
        Sanctum::actingAs($this->normalUser);

        $host1 = $this->createTestHost(['host_name' => 'web-server-01']);
        $host2 = $this->createTestHost(['host_name' => 'db-server-01']);

        $response = $this->getJson('/api/hosts');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
        
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    /// <summary>
    /// Test hosts filtering by status
    /// </summary>
    public function test_hosts_filtering_by_status(): void
    {
        Sanctum::actingAs($this->normalUser);

        $activeHost = $this->createTestHost(['is_active' => true]);
        $inactiveHost = $this->createTestHost(['is_active' => false]);

        $response = $this->getJson('/api/hosts?is_active=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($activeHost->host_id, $data[0]['host_id']);
    }

    #endregion

    #region UC24: Host Configuration Tests

    /// <summary>
    /// Test admin can update host configuration
    /// </summary>
    public function test_admin_can_update_host_configuration(): void
    {
        Sanctum::actingAs($this->adminUser);

        $host = $this->createTestHost();

        $configData = [
            'data_collection_interval' => 180,
            'enable_cpu_monitoring' => true,
            'enable_ram_monitoring' => true,
            'enable_disk_monitoring' => false,
            'enable_network_monitoring' => true
        ];

        $response = $this->putJson("/api/hosts/{$host->host_id}/configuration", $configData);

        $response->assertStatus(200)
                ->assertJsonPath('data.data_collection_interval', 180)
                ->assertJsonPath('data.enable_disk_monitoring', false);

        $this->assertDatabaseHas('host_configurations', [
            'host_id' => $host->host_id,
            'data_collection_interval' => 180,
            'enable_disk_monitoring' => false
        ]);
    }

    #endregion
}