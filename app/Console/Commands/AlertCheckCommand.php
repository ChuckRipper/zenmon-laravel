<?php

namespace App\Console\Commands;

use App\Models\{Host, Alert, MetricType, Metric};
use App\Services\{AlertService, NotificationService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Log, DB};
use Carbon\Carbon;

/// <summary>
/// Background command for checking alerts and agent connectivity
/// Runs independently of agent data submissions to ensure monitoring continuity
/// </summary>
class AlertCheckCommand extends Command
{
    #region Properties
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zenmon:check-alerts 
                            {--timeout=300 : Agent timeout in seconds (default: 5 minutes)}
                            {--dry-run : Run without creating alerts}
                            {--verbose : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check agent connectivity and generate alerts for offline hosts or missing metrics';

    /// <summary>
    /// Alert service for creating alerts
    /// </summary>
    private AlertService $alertService;
    
    /// <summary>
    /// Notification service for sending alerts
    /// </summary>
    private NotificationService $notificationService;
    
    /// <summary>
    /// Agent timeout threshold in seconds
    /// </summary>
    private int $timeoutThreshold;
    
    /// <summary>
    /// Dry run mode flag
    /// </summary>
    private bool $dryRun;
    
    /// <summary>
    /// Statistics for the check run
    /// </summary>
    private array $stats = [
        'hosts_checked' => 0,
        'hosts_online' => 0,
        'hosts_offline' => 0,
        'alerts_created' => 0,
        'alerts_resolved' => 0,
        'errors' => 0
    ];
    
    #endregion
    
    #region Constructor
    
    /// <summary>
    /// Initialize command with required services
    /// </summary>
    /// <param>AlertService $alertService</param>
    /// <param>NotificationService $notificationService</param>
    public function __construct(AlertService $alertService, NotificationService $notificationService)
    {
        parent::__construct();
        
        $this->alertService = $alertService;
        $this->notificationService = $notificationService;
    }
    
    #endregion
    
    #region Methods
    
    /// <summary>
    /// Execute the console command
    /// </summary>
    /// <returns>int</returns>
    public function handle(): int
    {
        try {
            $this->initializeCommand();
            
            $this->info('🔍 ZenMon Alert Check - Starting background monitoring...');
            
            if ($this->dryRun) {
                $this->warn('⚠️  DRY RUN MODE - No alerts will be created');
            }
            
            // Main monitoring tasks
            $this->checkAgentConnectivity();
            $this->checkStaleMetrics();
            $this->resolveObsoleteAlerts();
            
            // Summary
            $this->displaySummary();
            
            $this->info('✅ Alert check completed successfully');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Alert check failed: ' . $e->getMessage());
            
            Log::error('AlertCheckCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
    
    /// <summary>
    /// Initialize command parameters
    /// </summary>
    /// <returns>void</returns>
    private function initializeCommand(): void
    {
        $this->timeoutThreshold = (int) $this->option('timeout');
        $this->dryRun = $this->option('dry-run');
        
        if ($this->option('verbose')) {
            $this->info("Configuration:");
            $this->line("  - Timeout threshold: {$this->timeoutThreshold} seconds");
            $this->line("  - Dry run: " . ($this->dryRun ? 'Yes' : 'No'));
        }
    }
    
    /// <summary>
    /// Check agent connectivity and create alerts for offline hosts
    /// </summary>
    /// <returns>void</returns>
    private function checkAgentConnectivity(): void
    {
        $this->info('🔗 Checking agent connectivity...');
        
        $cutoffTime = Carbon::now()->subSeconds($this->timeoutThreshold);
        
        // Get all active hosts
        $hosts = Host::where('is_active', true)->get();
        
        foreach ($hosts as $host) {
            $this->stats['hosts_checked']++;
            
            if ($this->option('verbose')) {
                $this->line("  Checking host: {$host->hostname} ({$host->ip_address})");
            }
            
            try {
                // Get the most recent metric for this host
                $lastMetric = Metric::where('host_id', $host->host_id)
                                   ->orderBy('timestamp', 'desc')
                                   ->first();
                
                if (!$lastMetric || $lastMetric->timestamp < $cutoffTime) {
                    // Host is offline or hasn't sent data recently
                    $this->handleOfflineHost($host, $lastMetric);
                    $this->stats['hosts_offline']++;
                } else {
                    // Host is online
                    $this->handleOnlineHost($host);
                    $this->stats['hosts_online']++;
                }
                
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->error("Error checking host {$host->hostname}: " . $e->getMessage());
                
                Log::error('Error checking host connectivity', [
                    'host_id' => $host->host_id,
                    'hostname' => $host->hostname,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /// <summary>
    /// Handle offline host - create connectivity alert
    /// </summary>
    /// <param>Host $host</param>
    /// <param>Metric|null $lastMetric</param>
    /// <returns>void</returns>
    private function handleOfflineHost(Host $host, ?Metric $lastMetric): void
    {
        // Check if connectivity alert already exists
        $existingAlert = Alert::where('host_id', $host->host_id)
                             ->where('alert_level', 'Critical')
                             ->where('status', 'Active')
                             ->where('alert_message', 'LIKE', '%connectivity%')
                             ->first();
        
        if ($existingAlert) {
            if ($this->option('verbose')) {
                $this->line("    ⚠️  Connectivity alert already exists for {$host->hostname}");
            }
            return;
        }
        
        $lastSeenText = $lastMetric 
            ? "last seen " . $lastMetric->timestamp->diffForHumans()
            : "never seen";
        
        $alertMessage = "Agent connectivity lost for host {$host->hostname} ({$host->ip_address}) - {$lastSeenText}";
        
        if ($this->dryRun) {
            $this->warn("    [DRY RUN] Would create connectivity alert for {$host->hostname}");
            return;
        }
        
        // Create connectivity alert
        $alert = Alert::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $this->getConnectivityMetricTypeId(),
            'alert_level' => 'Critical',
            'alert_message' => $alertMessage,
            'current_value' => 0, // 0 = offline
            'threshold_value' => 1, // 1 = should be online
            'status' => 'Active'
        ]);
        
        $alert->load(['host', 'metricType']);
        
        // Send notification
        try {
            $this->notificationService->sendAlertNotification($alert);
        } catch (\Exception $e) {
            Log::error('Failed to send connectivity alert notification', [
                'alert_id' => $alert->alert_id,
                'error' => $e->getMessage()
            ]);
        }
        
        $this->stats['alerts_created']++;
        
        $this->warn("    🚨 Created connectivity alert for {$host->hostname}");
        
        Log::warning('Connectivity alert created', [
            'host_id' => $host->host_id,
            'hostname' => $host->hostname,
            'alert_id' => $alert->alert_id,
            'last_metric' => $lastMetric?->timestamp
        ]);
    }
    
    /// <summary>
    /// Handle online host - resolve connectivity alerts
    /// </summary>
    /// <param>Host $host</param>
    /// <returns>void</returns>
    private function handleOnlineHost(Host $host): void
    {
        // Check for active connectivity alerts to resolve
        $connectivityAlerts = Alert::where('host_id', $host->host_id)
                                  ->where('status', 'Active')
                                  ->where('alert_message', 'LIKE', '%connectivity%')
                                  ->get();
        
        foreach ($connectivityAlerts as $alert) {
            if ($this->dryRun) {
                $this->info("    [DRY RUN] Would resolve connectivity alert for {$host->hostname}");
                continue;
            }
            
            $alert->update([
                'status' => 'Resolved',
                'resolved_date' => now(),
                'resolution_comment' => 'Agent connectivity restored - automatic resolution'
            ]);
            
            $alert->load(['host', 'metricType']);
            
            // Send resolved notification
            try {
                $this->notificationService->sendAlertResolvedNotification($alert);
            } catch (\Exception $e) {
                Log::error('Failed to send connectivity resolved notification', [
                    'alert_id' => $alert->alert_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $this->stats['alerts_resolved']++;
            
            $this->info("    ✅ Resolved connectivity alert for {$host->hostname}");
            
            Log::info('Connectivity alert resolved', [
                'host_id' => $host->host_id,
                'hostname' => $host->hostname,
                'alert_id' => $alert->alert_id
            ]);
        }
        
        if ($this->option('verbose')) {
            $this->line("    ✓ Host {$host->hostname} is online");
        }
    }
    
    /// <summary>
    /// Check for stale metrics and create alerts if needed
    /// </summary>
    /// <returns>void</returns>
    private function checkStaleMetrics(): void
    {
        $this->info('📊 Checking for stale metrics...');
        
        $staleThreshold = Carbon::now()->subMinutes(30); // 30 minutes
        
        // Get hosts that are online but have very old metrics for critical metric types
        $criticalMetricTypes = ['CPU Usage', 'Memory Usage', 'Disk Usage'];
        
        $hosts = Host::where('is_active', true)->get();
        
        foreach ($hosts as $host) {
            foreach ($criticalMetricTypes as $metricTypeName) {
                $metricType = MetricType::where('type_name', $metricTypeName)->first();
                
                if (!$metricType) continue;
                
                $lastMetric = Metric::where('host_id', $host->host_id)
                                   ->where('metric_type_id', $metricType->metric_type_id)
                                   ->orderBy('timestamp', 'desc')
                                   ->first();
                
                if ($lastMetric && $lastMetric->timestamp < $staleThreshold) {
                    $this->handleStaleMetric($host, $metricType, $lastMetric);
                }
            }
        }
    }
    
    /// <summary>
    /// Handle stale metric detection
    /// </summary>
    /// <param>Host $host</param>
    /// <param>MetricType $metricType</param>
    /// <param>Metric $lastMetric</param>
    /// <returns>void</returns>
    private function handleStaleMetric(Host $host, MetricType $metricType, Metric $lastMetric): void
    {
        // Check if stale metric alert already exists
        $existingAlert = Alert::where('host_id', $host->host_id)
                             ->where('metric_type_id', $metricType->metric_type_id)
                             ->where('status', 'Active')
                             ->where('alert_message', 'LIKE', '%stale%')
                             ->first();
        
        if ($existingAlert) {
            return;
        }
        
        $alertMessage = "Stale {$metricType->type_name} data for {$host->hostname} - last update {$lastMetric->timestamp->diffForHumans()}";
        
        if ($this->dryRun) {
            $this->warn("    [DRY RUN] Would create stale metric alert: {$alertMessage}");
            return;
        }
        
        $alert = Alert::create([
            'host_id' => $host->host_id,
            'metric_type_id' => $metricType->metric_type_id,
            'alert_level' => 'Warning',
            'alert_message' => $alertMessage,
            'current_value' => $lastMetric->timestamp->timestamp,
            'threshold_value' => Carbon::now()->subMinutes(15)->timestamp,
            'status' => 'Active'
        ]);
        
        $this->stats['alerts_created']++;
        
        Log::warning('Stale metric alert created', [
            'host_id' => $host->host_id,
            'metric_type_id' => $metricType->metric_type_id,
            'alert_id' => $alert->alert_id,
            'last_metric_time' => $lastMetric->timestamp
        ]);
    }
    
    /// <summary>
    /// Resolve alerts that are no longer relevant
    /// </summary>
    /// <returns>void</returns>
    private function resolveObsoleteAlerts(): void
    {
        $this->info('🧹 Cleaning up obsolete alerts...');
        
        // Auto-resolve very old active alerts (older than 24 hours)
        $oldAlerts = Alert::where('status', 'Active')
                         ->where('created_at', '<', Carbon::now()->subDay())
                         ->get();
        
        foreach ($oldAlerts as $alert) {
            if ($this->dryRun) {
                $this->line("    [DRY RUN] Would auto-resolve old alert #{$alert->alert_id}");
                continue;
            }
            
            $alert->update([
                'status' => 'Resolved',
                'resolved_date' => now(),
                'resolution_comment' => 'Auto-resolved due to age (24+ hours old)'
            ]);
            
            $this->stats['alerts_resolved']++;
        }
        
        if (count($oldAlerts) > 0) {
            $this->info("    ✅ Auto-resolved " . count($oldAlerts) . " old alerts");
        }
    }
    
    /// <summary>
    /// Get or create connectivity metric type
    /// </summary>
    /// <returns>int</returns>
    private function getConnectivityMetricTypeId(): int
    {
        $metricType = MetricType::firstOrCreate(
            ['type_name' => 'Agent Connectivity'],
            [
                'description' => 'Agent connection status monitoring',
                'unit' => 'status',
                'data_type' => 'integer'
            ]
        );
        
        return $metricType->metric_type_id;
    }
    
    /// <summary>
    /// Display command execution summary
    /// </summary>
    /// <returns>void</returns>
    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('📋 Alert Check Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Hosts Checked', $this->stats['hosts_checked']],
                ['Hosts Online', $this->stats['hosts_online']],
                ['Hosts Offline', $this->stats['hosts_offline']],
                ['Alerts Created', $this->stats['alerts_created']],
                ['Alerts Resolved', $this->stats['alerts_resolved']],
                ['Errors', $this->stats['errors']]
            ]
        );
        
        Log::info('AlertCheckCommand completed', $this->stats);
    }
    
    #endregion
}