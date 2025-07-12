<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Host;
use App\Models\MetricType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/// <summary>
/// Test suite for Agent API endpoints
/// Validates UC31 functionality with Bearer token authentication
/// </summary>
class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    #region Setup

    /// <summary>
    /// Set up test environment
    /// </summary>
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test agent user
        $this->agentUser = User::create([
            'login' => 'agent_test',
            'password' => Hash::make('agent_pass'),
            'email' => 'agent@test.local',
            'first_name' => 'Agent',
            'last_name' => 'Test',
            'role' => 'User',
            'is_active' => true
        ]);

        // Create test host
        $this->testHost = Host::create([
            'host_name' => 'test-agent-host',
            'ip_address' => '192.168.1.100',
            'description' => 'Test host for agent',
            'operating_system' => 'Ubuntu 22.04',
            'agent_version' => '2.0',
            'is_active' => true
        ]);

        // Create metric types
        $this->metricTypes = [
            MetricType::create(['metric_name' => 'CPU', 'unit' => '%', 'description' => 'CPU Usage']),
            MetricType::create(['metric_name' => 'Memory', 'unit' => '%', 'description' => 'Memory Usage']),
            MetricType::create(['metric_name' => 'Disk', 'unit' => '%', 'description' => 'Disk Usage']),
            MetricType::create(['metric_name' => 'Network', 'unit' => 'ms', 'description' => 'Network Response Time'])
        ];
    }

    #endregion

    #region Agent Authentication Tests

    /// <summary>
    /// Test agent can authenticate and receive token
    /// </summary>
    public function test_agent_authentication(): void
    {
        $response = $this->postJson('/api/login', [
            'login' => 'agent_test',
            'password' => 'agent_pass'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure(['message', 'token', 'user'])
                ->assertJson(['message' => 'Login successful']);

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertStringStartsWith('1|', $token); // Sanctum token format
    }

    #endregion

    #region Metrics Batch Endpoint Tests

    /// <summary>
    /// Test agent can submit metrics batch with authentication (UC31)
    /// </summary>
    public function test_agent_can_submit_metrics_batch(): void
    {
        Sanctum::actingAs($this->agentUser);

        $metricsPayload = [
            'metrics' => [
                [
                    'host_id' => $this->testHost->host_id,
                    'metric_type_id' => 1, // CPU
                    'value' => 45.5,
                    'timestamp' => now()->toISOString(),
                    'additional_info' => [
                        'hostname' => 'test-host',
                        'cpu_count' => 4
                    ]
                ],
                [
                    'host_id' => $this->testHost->host_id,
                    'metric_type_id' => 2, // Memory  
                    'value' => 67.2,
                    'timestamp' => now()->toISOString(),
                    'additional_info' => [
                        'hostname' => 'test-host',
                        'total_gb' => 16
                    ]
                ]
            ],
            'agent_info' => [
                'version' => '2.0',
                'platform' => 'Linux',
                'hostname' => 'test-host'
            ]
        ];

        $response = $this->postJson('/api/agent/metrics/batch', $metricsPayload);

        $response->assertStatus(201);
        
        // Verify metrics were stored in database
        $this->assertDatabaseCount('metrics', 2);
        $this->assertDatabaseHas('metrics', [
            'host_id' => $this->testHost->host_id,
            'metric_type_id' => 1,
            'value' => 45.5
        ]);
    }

    /// <summary>
    /// Test metrics batch endpoint requires authentication
    /// </summary>
    public function test_metrics_batch_requires_authentication(): void
    {
        $response = $this->postJson('/api/agent/metrics/batch', [
            'metrics' => [],
            'agent_info' => ['version' => '2.0']
        ]);

        $response->assertStatus(401)
                ->assertJson(['message' => 'Unauthenticated']);
    }

    /// <summary>
    /// Test metrics batch validation
    /// </summary>
    public function test_metrics_batch_validation(): void
    {
        Sanctum::actingAs($this->agentUser);

        // Test with invalid payload
        $response = $this->postJson('/api/agent/metrics/batch', [
            'metrics' => [
                [
                    'host_id' => 999, // Non-existent host
                    'metric_type_id' => 1,
                    'value' => 'invalid', // Should be numeric
                ]
            ]
        ]);

        $response->assertStatus(422);
    }

    #endregion

    #region Heartbeat Endpoint Tests

    /// <summary>
    /// Test agent can send heartbeat with authentication
    /// </summary>
    public function test_agent_can_send_heartbeat(): void
    {
        Sanctum::actingAs($this->agentUser);

        $heartbeatPayload = [
            'timestamp' => now()->toISOString(),
            'status' => 'online',
            'agent_version' => '2.0'
        ];

        $response = $this->postJson("/api/agent/heartbeat/{$this->testHost->host_id}", $heartbeatPayload);

        $response->assertStatus(200);
    }

    /// <summary>
    /// Test heartbeat endpoint requires authentication
    /// </summary>
    public function test_heartbeat_requires_authentication(): void
    {
        $response = $this->postJson("/api/agent/heartbeat/{$this->testHost->host_id}", [
            'timestamp' => now()->toISOString(),
            'status' => 'online'
        ]);

        $response->assertStatus(401);
    }

    #endregion

    #region Directory Metrics Tests

    /// <summary>
    /// Test agent can submit directory metrics with authentication
    /// </summary>
    public function test_agent_can_submit_directory_metrics(): void
    {
        Sanctum::actingAs($this->agentUser);

        $directoryPayload = [
            'directory_metrics' => [
                [
                    'host_id' => $this->testHost->host_id,
                    'directory_path' => '/var/log',
                    'size_bytes' => 1048576,
                    'file_count' => 150,
                    'timestamp' => now()->toISOString(),
                    'additional_info' => [
                        'size_mb' => 1.0,
                        'largest_file_bytes' => 102400
                    ]
                ]
            ],
            'agent_info' => [
                'version' => '2.0',
                'platform' => 'Linux'
            ]
        ];

        $response = $this->postJson('/api/agent/directory-metrics', $directoryPayload);

        $response->assertStatus(201);
        
        // Verify directory metrics were stored
        $this->assertDatabaseCount('directory_metrics', 1);
        $this->assertDatabaseHas('directory_metrics', [
            'host_id' => $this->testHost->host_id,
            'directory_path' => '/var/log',
            'size_bytes' => 1048576
        ]);
    }

    /// <summary>
    /// Test directory metrics endpoint requires authentication
    /// </summary>
    public function test_directory_metrics_requires_authentication(): void
    {
        $response = $this->postJson('/api/agent/directory-metrics', [
            'directory_metrics' => []
        ]);

        $response->assertStatus(401);
    }

    #endregion

    #region Agent Integration Tests

    /// <summary>
    /// Test complete agent workflow: login -> submit metrics -> heartbeat
    /// </summary>
    public function test_complete_agent_workflow(): void
    {
        // Step 1: Agent authenticates
        $loginResponse = $this->postJson('/api/login', [
            'login' => 'agent_test',
            'password' => 'agent_pass'
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');

        // Step 2: Agent submits metrics using token
        $metricsResponse = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->postJson('/api/agent/metrics/batch', [
            'metrics' => [
                [
                    'host_id' => $this->testHost->host_id,
                    'metric_type_id' => 1,
                    'value' => 55.0,
                    'timestamp' => now()->toISOString()
                ]
            ],
            'agent_info' => ['version' => '2.0']
        ]);

        $metricsResponse->assertStatus(201);

        // Step 3: Agent sends heartbeat
        $heartbeatResponse = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->postJson("/api/agent/heartbeat/{$this->testHost->host_id}", [
            'timestamp' => now()->toISOString(),
            'status' => 'online'
        ]);

        $heartbeatResponse->assertStatus(200);

        // Verify data was stored correctly
        $this->assertDatabaseCount('metrics', 1);
    }

    /// <summary>
    /// Test agent with expired/invalid token receives proper error
    /// </summary>
    public function test_agent_with_invalid_token_gets_proper_error(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-12345'
        ])->postJson('/api/agent/metrics/batch', [
            'metrics' => []
        ]);

        $response->assertStatus(401)
                ->assertJsonStructure([
                    'message',
                    'error',
                    'hint',
                    'endpoints'
                ])
                ->assertJson([
                    'message' => 'Unauthenticated',
                    'hint' => 'Obtain new token via POST /api/login'
                ]);
    }

    #endregion

    #region Performance Tests

    /// <summary>
    /// Test agent can submit large batch of metrics
    /// </summary>
    public function test_agent_can_submit_large_metrics_batch(): void
    {
        Sanctum::actingAs($this->agentUser);

        // Generate 100 metrics
        $metrics = [];
        for ($i = 0; $i < 100; $i++) {
            $metrics[] = [
                'host_id' => $this->testHost->host_id,
                'metric_type_id' => ($i % 4) + 1, // Cycle through metric types
                'value' => rand(10, 100),
                'timestamp' => now()->addSeconds($i)->toISOString(),
                'additional_info' => ['test_batch' => true]
            ];
        }

        $response = $this->postJson('/api/agent/metrics/batch', [
            'metrics' => $metrics,
            'agent_info' => ['version' => '2.0']
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('metrics', 100);
    }

    #endregion
}