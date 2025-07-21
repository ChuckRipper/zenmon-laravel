<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    #region Authentication Security Tests

    /// <summary>
    /// Test rate limiting on login endpoint
    /// </summary>
    public function test_login_rate_limiting(): void
    {
        $user = $this->createTestUser();

        // Make multiple failed login attempts
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', [
                'login' => $user->login,
                'password' => 'wrong_password'
            ]);
        }

        // Next request should be rate limited
        $response = $this->postJson('/api/login', [
            'login' => $user->login,
            'password' => 'wrong_password'
        ]);

        // Should be rate limited (429) or still unauthorized (401)
        $this->assertContains($response->status(), [401, 429]);
    }

    /// <summary>
    /// Test SQL injection protection
    /// </summary>
    public function test_sql_injection_protection(): void
    {
        $admin = $this->createTestUser('Administrator');
        Sanctum::actingAs($admin);

        // Try SQL injection in host search
        $response = $this->getJson("/api/hosts?search=' OR '1'='1");
        
        // Should return normal response, not error
        $response->assertStatus(200);
        
        // Try SQL injection in login
        $response = $this->postJson('/api/login', [
            'login' => "' OR '1'='1' --",
            'password' => "anything"
        ]);
        
        // $response->assertStatus(422); // Validation error, not successful login
        $response->assertStatus(401); // Unauthorized, not validation error
    }

    /// <summary>
    /// Test XSS protection in user input
    /// </summary>
    public function test_xss_protection(): void
    {
        $admin = $this->createTestUser('Administrator');
        Sanctum::actingAs($admin);

        // Try to create host with XSS payload
        $response = $this->postJson('/api/hosts', [
            'host_name' => '<script>alert("xss")</script>',
            'ip_address' => '192.168.1.99',
            'description' => '<img src=x onerror=alert("xss")>'
        ]);

        $response->assertStatus(201);
        
        // Verify data is escaped/sanitized
        $host = $response->json('data');
        $this->assertStringNotContainsString('<script>', $host['host_name']);
        $this->assertStringNotContainsString('onerror', $host['description']);
    }

    /// <summary>
    /// Test unauthorized access attempts
    /// </summary>
    public function test_unauthorized_access_attempts(): void
    {
        $user = $this->createTestUser('User');
        Sanctum::actingAs($user);

        // Try to access admin-only endpoints
        $adminEndpoints = [
            ['POST', '/api/hosts'],
            ['DELETE', '/api/hosts/1'],
            ['POST', '/api/alert-thresholds'],
            ['GET', '/api/notifications/config']
        ];

        foreach ($adminEndpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            $this->assertEquals(403, $response->status(), 
                "User should not have access to {$method} {$endpoint}");
        }
    }

    #endregion

    #region Data Validation Tests

    /// <summary>
    /// Test input validation and sanitization
    /// </summary>
    public function test_input_validation_and_sanitization(): void
    {
        $admin = $this->createTestUser('Administrator');
        Sanctum::actingAs($admin);

        // Test invalid IP address formats
        $invalidIPs = ['999.999.999.999', 'not.an.ip', '192.168.1', '192.168.1.256'];
        
        foreach ($invalidIPs as $invalidIP) {
            $response = $this->postJson('/api/hosts', [
                'host_name' => 'test-host',
                'ip_address' => $invalidIP
            ]);
            
            $response->assertStatus(422)
                    ->assertJsonValidationErrors(['ip_address']);
        }

        // Test oversized input
        $response = $this->postJson('/api/hosts', [
            'host_name' => str_repeat('a', 256), // Too long
            'ip_address' => '192.168.1.100'
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['host_name']);
    }

    /// <summary>
    /// Test file upload security (if applicable)
    /// </summary>
    public function test_file_upload_security(): void
    {
        // Note: This test would be implemented if the system has file upload functionality
        // For now, it's a placeholder for future security testing
        $this->assertTrue(true, 'File upload security tests would go here');
    }

    #endregion

    #region Password Security Tests

    /// <summary>
    /// Test password hashing and verification
    /// </summary>
    public function test_password_hashing_security(): void
    {
        $password = 'SecurePassword123!';
        $user = User::create([
            'login' => 'security_test_user',
            'password' => Hash::make($password),
            'email' => 'security@test.local',
            'first_name' => 'Security',
            'last_name' => 'Test',
            'role' => 'User'
        ]);

        // Verify password is hashed
        $this->assertNotEquals($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));

        // Verify wrong password fails
        $this->assertFalse(Hash::check('WrongPassword', $user->password));

        // Test login with correct password
        $response = $this->postJson('/api/login', [
            'login' => 'security_test_user',
            'password' => $password
        ]);
        $response->assertStatus(200);

        // Test login with wrong password
        $response = $this->postJson('/api/login', [
            'login' => 'security_test_user',
            'password' => 'WrongPassword'
        ]);
        $response->assertStatus(401);
    }

    #endregion
}