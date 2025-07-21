<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/// <summary>
/// Test suite for API security and authentication
/// Validates that endpoints are properly secured with Bearer token authentication
/// </summary>
class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    #region Setup

    /// <summary>
    /// Set up test environment with sample users
    /// </summary>
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test admin user
        $this->admin = User::create([
            'login' => 'testadmin',
            'password' => Hash::make('testpass'),
            'email' => 'admin@test.local',
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'role' => 'Administrator',
            'is_active' => true
        ]);

        // Create test regular user  
        $this->user = User::create([
            'login' => 'testuser',
            'password' => Hash::make('testpass'),
            'email' => 'user@test.local',
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'User',
            'is_active' => true
        ]);
    }

    #endregion

    #region Authentication Tests

    /// <summary>
    /// Test successful authentication with valid credentials
    /// </summary>
    public function test_successful_authentication(): void
    {
        $response = $this->postJson('/api/login', [
            'login' => 'testadmin',
            'password' => 'testpass'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'token',
                    'user' => ['id', 'login', 'full_name', 'role']
                ])
                ->assertJson([
                    'message' => 'Login successful',
                    'user' => [
                        'login' => 'testadmin',
                        'role' => 'Administrator'
                    ]
                ]);
        
        $this->assertNotEmpty($response->json('token'));
    }

    /// <summary>
    /// Test authentication failure with invalid credentials
    /// </summary>
    public function test_authentication_failure(): void
    {
        $response = $this->postJson('/api/login', [
            'login' => 'testadmin',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(401)
                ->assertJson(['message' => 'Invalid credentials']);
    }

    /// <summary>
    /// Test authentication with missing credentials
    /// </summary>
    public function test_authentication_validation(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['login', 'password']);
    }

    #endregion

    #region Public Endpoints Tests

    /// <summary>
    /// Test that public endpoints work without authentication
    /// </summary>
    public function test_public_endpoints_accessible(): void
    {
        // Health check
        $response = $this->getJson('/api/public/health');
        $response->assertStatus(200)
                ->assertJsonStructure(['status', 'service', 'version', 'timestamp']);

        // Host count
        $response = $this->getJson('/api/public/hosts/count');
        $response->assertStatus(200)
                ->assertJsonStructure(['total_hosts', 'active_hosts', 'timestamp']);

        // Alerts summary
        $response = $this->getJson('/api/public/alerts/summary');
        $response->assertStatus(200)
                ->assertJsonStructure(['total_alerts', 'active_alerts', 'timestamp']);

        // Metrics summary
        $response = $this->getJson('/api/public/metrics/summary');
        $response->assertStatus(200)
                ->assertJsonStructure(['total_metrics', 'recent_metrics', 'timestamp']);
    }

    #endregion

    #region Protected Endpoints Tests

    /// <summary>
    /// Test that protected endpoints require authentication
    /// </summary>
    public function test_protected_endpoints_require_auth(): void
    {
        $protectedEndpoints = [
            ['GET', '/api/user'],
            ['GET', '/api/hosts'],
            ['GET', '/api/metrics'],
            ['GET', '/api/alerts'],
            ['POST', '/api/agent/metrics/batch'],
            ['POST', '/api/agent/heartbeat/1'],
            ['GET', '/api/test/database'],
        ];

        foreach ($protectedEndpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            
            $response->assertStatus(401)
                    ->assertJsonStructure([
                        'message',
                        'error', 
                        'hint',
                        'endpoints',
                        'timestamp'
                    ])
                    ->assertJson(['message' => 'Unauthenticated']);
        }
    }

    /// <summary>
    /// Test protected endpoints with invalid token
    /// </summary>
    public function test_protected_endpoints_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-12345'
        ])->getJson('/api/user');

        $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Unauthenticated',
                    'error' => 'Invalid or expired token'
                ]);
    }

    /// <summary>
    /// Test protected endpoints with malformed Authorization header
    /// </summary>
    public function test_protected_endpoints_with_malformed_header(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Token abc123'  // Wrong format
        ])->getJson('/api/user');

        $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Unauthenticated',
                    // 'error' => 'Invalid Authorization header format'
                    'error' => 'Invalid or expired token'
                ]);
    }

    #endregion

    #region Authenticated Endpoints Tests

    /// <summary>
    /// Test that authenticated user can access protected endpoints
    /// </summary>
    public function test_authenticated_user_can_access_protected_endpoints(): void
    {
        Sanctum::actingAs($this->user);

        // Test user endpoint
        $response = $this->getJson('/api/user');
        $response->assertStatus(200)
                ->assertJson(['login' => 'testuser']);

        // Test database test endpoint
        $response = $this->getJson('/api/test/database');
        $response->assertStatus(200)
                ->assertJsonStructure(['message', 'timestamp', 'authenticated_user']);

        // Test auth test endpoint
        $response = $this->getJson('/api/test/auth');
        $response->assertStatus(200)
                ->assertJsonStructure(['message', 'user', 'timestamp']);
    }

    #endregion

    #region Agent Endpoints Tests

    /// <summary>
    /// Test agent endpoints require authentication (UC31 security)
    /// </summary>
    public function test_agent_endpoints_require_authentication(): void
    {
        $agentEndpoints = [
            ['POST', '/api/agent/metrics/batch', ['metrics' => []]],
            ['POST', '/api/agent/directory-metrics', ['directory_metrics' => []]],
            ['POST', '/api/agent/heartbeat/1', ['timestamp' => now()->toISOString()]],
        ];

        foreach ($agentEndpoints as [$method, $endpoint, $payload]) {
            // Test without token
            $response = $this->json($method, $endpoint, $payload);
            $response->assertStatus(401);

            // Test with valid token
            Sanctum::actingAs($this->user);
            $response = $this->json($method, $endpoint, $payload);
            // Should not be 401 (might be 404, 422, etc. depending on implementation)
            $response->assertStatus('!=', 401);
        }
    }

    #endregion

    #region Administrator Tests

    /// <summary>
    /// Test that inactive user cannot authenticate
    /// </summary>
    public function test_inactive_user_cannot_access_endpoints(): void
    {
        $this->user->update(['is_active' => false]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/user');
        $response->assertStatus(403)
                ->assertJson([
                    // 'message' => 'Account disabled',
                    'message' => 'Forbidden',
                    'error' => 'User account has been deactivated'
                ]);
    }

    /// <summary>
    /// Test rate limiting on login endpoint
    /// </summary>
    public function test_login_rate_limiting(): void
    {
        // Make multiple rapid requests to trigger rate limiting
        for ($i = 0; $i < 70; $i++) {
            $this->postJson('/api/login', [
                'login' => 'testadmin',
                'password' => 'wrongpassword'
            ]);
        }

        // The next request should be rate limited
        $response = $this->postJson('/api/login', [
            'login' => 'testadmin', 
            'password' => 'testpass'
        ]);

        $response->assertStatus(429)
                ->assertJsonStructure([
                    'message',
                    'error',
                    'retry_after',
                    'timestamp'
                ]);
    }

    #endregion

    #region Token Validation Tests

    /// <summary>
    /// Test that token works for subsequent requests after login
    /// </summary>
    public function test_token_works_for_subsequent_requests(): void
    {
        // Login and get token
        $loginResponse = $this->postJson('/api/login', [
            'login' => 'testadmin',
            'password' => 'testpass'
        ]);

        $token = $loginResponse->json('token');

        // Use token for authenticated request
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->getJson('/api/user');

        $response->assertStatus(200)
                ->assertJson(['login' => 'testadmin']);
    }

    #endregion
}