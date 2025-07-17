<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class SwaggerDocumentationTest extends TestCase
{
    use RefreshDatabase;

    #region Swagger Documentation Tests

    /// <summary>
    /// Test Swagger documentation is accessible
    /// </summary>
    public function test_swagger_documentation_accessible(): void
    {
        $response = $this->get('/api/documentation');
        $response->assertStatus(200);
        $response->assertSee('ZenMon API Documentation');
    }

    /// <summary>
    /// Test Swagger JSON is valid
    /// </summary>
    public function test_swagger_json_is_valid(): void
    {
        $response = $this->get('/docs/api-docs.json');
        $response->assertStatus(200);
        
        $json = $response->json();
        $this->assertArrayHasKey('openapi', $json);
        $this->assertArrayHasKey('info', $json);
        $this->assertArrayHasKey('paths', $json);
        
        // Verify basic API info
        $this->assertEquals('ZenMon API', $json['info']['title']);
        $this->assertArrayHasKey('version', $json['info']);
    }

    /// <summary>
    /// Test all documented endpoints exist
    /// </summary>
    public function test_documented_endpoints_exist(): void
    {
        $admin = $this->createTestUser('Administrator');
        Sanctum::actingAs($admin);

        // Get Swagger documentation
        $response = $this->get('/docs/api-docs.json');
        $swagger = $response->json();

        // Test a sample of documented endpoints
        $endpointsToTest = [
            ['GET', '/api/hosts'],
            ['GET', '/api/metrics'],
            ['GET', '/api/alerts'],
            ['GET', '/api/public/health']
        ];

        foreach ($endpointsToTest as [$method, $path]) {
            if (isset($swagger['paths'][$path][strtolower($method)])) {
                $response = $this->json($method, $path);
                
                // Should not return 404 (endpoint not found)
                $this->assertNotEquals(404, $response->status(), 
                    "Documented endpoint {$method} {$path} returns 404");
            }
        }
    }

    #endregion
}