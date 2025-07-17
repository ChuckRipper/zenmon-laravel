<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Host, Metric, MetricType};
use Carbon\Carbon;

class MetricApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Host $host;
    private array $metricTypes;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = $this->createTestUser();
        $this->host = $this->createTestHost();
        $this->metricTypes = $this->createTestMetricTypes();
    }

    #region UC32: View Metrics Tests

    /// <summary>
    /// Test users can view metrics list
    /// </summary>
    public function test_users_can_view_metrics_list(): void
    {
        Sanctum::actingAs($this->user);

        // Create test metrics
        $metrics = collect();
        for ($i = 0; $i < 5; $i++) {
            $metrics->push(Metric::create([
                'host_id' => $this->host->host_id,
                'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
                'value' => 50.0 + $i * 5,
                'timestamp' => now()->subMinutes($i * 10)
            ]));
        }

        $response = $this->getJson('/api/metrics');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
        
        $data = $response->json('data');
        $this->assertCount(5, $data);
    }

    /// <summary>
    /// Test metrics filtering by host
    /// </summary>
    public function test_metrics_filtering_by_host(): void
    {
        Sanctum::actingAs($this->user);

        $host2 = $this->createTestHost();

        // Create metrics for both hosts
        Metric::create([
            'host_id' => $this->host->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'value' => 50.0,
            'timestamp' => now()
        ]);

        Metric::create([
            'host_id' => $host2->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'value' => 60.0,
            'timestamp' => now()
        ]);

        $response = $this->getJson("/api/metrics?host_id={$this->host->host_id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->host->host_id, $data[0]['host_id']);
    }

    /// <summary>
    /// Test metrics filtering by metric type
    /// </summary>
    public function test_metrics_filtering_by_metric_type(): void
    {
        Sanctum::actingAs($this->user);

        // Create CPU and RAM metrics
        Metric::create([
            'host_id' => $this->host->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'value' => 50.0,
            'timestamp' => now()
        ]);

        Metric::create([
            'host_id' => $this->host->host_id,
            'metric_type_id' => $this->metricTypes['ram']->metric_type_id,
            'value' => 70.0,
            'timestamp' => now()
        ]);

        $response = $this->getJson("/api/metrics?metric_type_id={$this->metricTypes['cpu']->metric_type_id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->metricTypes['cpu']->metric_type_id, $data[0]['metric_type_id']);
    }

    /// <summary>
    /// Test metrics filtering by date range
    /// </summary>
    public function test_metrics_filtering_by_date_range(): void
    {
        Sanctum::actingAs($this->user);

        // Create metrics at different times
        $oldMetric = Metric::create([
            'host_id' => $this->host->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'value' => 50.0,
            'timestamp' => now()->subDays(2)
        ]);

        $recentMetric = Metric::create([
            'host_id' => $this->host->host_id,
            'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
            'value' => 60.0,
            'timestamp' => now()->subHours(1)
        ]);

        $fromDate = now()->subHours(2)->toISOString();
        $toDate = now()->toISOString();

        $response = $this->getJson("/api/metrics?from_date={$fromDate}&to_date={$toDate}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($recentMetric->metric_id, $data[0]['metric_id']);
    }

    #endregion

    #region UC33: Historical Data Tests

    /// <summary>
    /// Test getting latest metrics for dashboard
    /// </summary>
    public function test_get_latest_metrics_for_dashboard(): void
    {
        Sanctum::actingAs($this->user);

        // Create metrics over time
        $timestamps = [
            now()->subMinutes(30),
            now()->subMinutes(20),
            now()->subMinutes(10),
            now()
        ];

        foreach ($timestamps as $timestamp) {
            Metric::create([
                'host_id' => $this->host->host_id,
                'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
                'value' => rand(40, 80),
                'timestamp' => $timestamp
            ]);
        }

        $response = $this->getJson('/api/metrics?latest=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        
        // Should return the most recent metric
        $latestTimestamp = $data[0]['timestamp'];
        $this->assertEquals($timestamps[3]->format('Y-m-d H:i:s'), 
                           Carbon::parse($latestTimestamp)->format('Y-m-d H:i:s'));
    }

    /// <summary>
    /// Test getting metrics trend data
    /// </summary>
    public function test_get_metrics_trend_data(): void
    {
        Sanctum::actingAs($this->user);

        // Create hourly metrics for last 6 hours
        for ($i = 6; $i >= 1; $i--) {
            Metric::create([
                'host_id' => $this->host->host_id,
                'metric_type_id' => $this->metricTypes['cpu']->metric_type_id,
                'value' => 40 + ($i * 5), // Increasing values
                'timestamp' => now()->subHours($i)
            ]);
        }

        $response = $this->getJson("/api/metrics?host_id={$this->host->host_id}&metric_type_id={$this->metricTypes['cpu']->metric_type_id}&hours=6");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(6, $data);
        
        // Verify data is sorted by timestamp
        $timestamps = collect($data)->pluck('timestamp')->toArray();
        $this->assertEquals($timestamps, collect($timestamps)->sort()->values()->toArray());
    }

    #endregion
}