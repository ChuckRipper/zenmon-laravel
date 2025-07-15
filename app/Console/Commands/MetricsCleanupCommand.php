<?php

namespace App\Console\Commands;

use App\Models\{Metric, Alert, DirectoryMetric};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Log};
use Carbon\Carbon;

/// <summary>
/// Command for cleaning up old metrics and optimizing database performance
/// Removes old raw metrics while preserving aggregated data and important alerts
/// </summary>
class MetricsCleanupCommand extends Command
{
    #region Properties
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'zenmon:cleanup-metrics 
    //                         {--days=30 : Keep metrics for specified days (default: 30)}
    //                         {--alert-days=180 : Keep alerts for specified days (default: 180)}
    //                         {--dry-run : Run without actually deleting data}
    //                         {--verbose : Show detailed output}
    //                         {--batch-size=1000 : Number of records to delete per batch}';
    protected $signature = 'zenmon:cleanup-metrics 
                        {--days=30 : Keep metrics for specified days (default: 30)}
                        {--alert-days=180 : Keep alerts for specified days (default: 180)}
                        {--dry-run : Run without actually deleting data}
                        {--show-details : Show detailed output}
                        {--batch-size=1000 : Number of records to delete per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old metrics data to optimize database performance';

    /// <summary>
    /// Number of days to keep raw metrics
    /// </summary>
    private int $retentionDays;
    
    /// <summary>
    /// Number of days to keep alerts
    /// </summary>
    private int $alertRetentionDays;
    
    /// <summary>
    /// Dry run mode flag
    /// </summary>
    private bool $dryRun;
    
    /// <summary>
    /// Batch size for deletions
    /// </summary>
    private int $batchSize;
    
    /// <summary>
    /// Cleanup statistics
    /// </summary>
    private array $stats = [
        'metrics_deleted' => 0,
        'directory_metrics_deleted' => 0,
        'alerts_deleted' => 0,
        'disk_space_freed_mb' => 0,
        'execution_time_seconds' => 0,
        'batches_processed' => 0
    ];
    
    #endregion
    
    #region Methods
    
    /// <summary>
    /// Execute the console command
    /// </summary>
    /// <returns>int</returns>
    public function handle(): int
    {
        $startTime = microtime(true);
        
        try {
            $this->initializeCommand();
            
            $this->info('🧹 ZenMon Metrics Cleanup - Starting database optimization...');
            
            if ($this->dryRun) {
                $this->warn('⚠️  DRY RUN MODE - No data will be deleted');
            }
            
            // Display current database stats
            $this->displayDatabaseStats();
            
            // Main cleanup tasks
            $this->cleanupOldMetrics();
            $this->cleanupOldDirectoryMetrics();
            $this->cleanupOldAlerts();
            $this->optimizeTables();
            
            // Calculate execution time
            $this->stats['execution_time_seconds'] = round(microtime(true) - $startTime, 2);
            
            // Display summary
            $this->displaySummary();
            
            $this->info('✅ Metrics cleanup completed successfully');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Metrics cleanup failed: ' . $e->getMessage());
            
            Log::error('MetricsCleanupCommand failed', [
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
        $this->retentionDays = (int) $this->option('days');
        $this->alertRetentionDays = (int) $this->option('alert-days');
        $this->dryRun = $this->option('dry-run');
        $this->batchSize = (int) $this->option('batch-size');
        
        // Validate parameters
        if ($this->retentionDays < 1 || $this->retentionDays > 3650) {
            throw new \InvalidArgumentException('Retention days must be between 1 and 3650');
        }
        
        if ($this->alertRetentionDays < 1 || $this->alertRetentionDays > 3650) {
            throw new \InvalidArgumentException('Alert retention days must be between 1 and 3650');
        }
        
        if ($this->batchSize < 100 || $this->batchSize > 10000) {
            throw new \InvalidArgumentException('Batch size must be between 100 and 10000');
        }
        
        if ($this->option('show-details')) {
            $this->info("Configuration:");
            $this->line("  - Metrics retention: {$this->retentionDays} days");
            $this->line("  - Alerts retention: {$this->alertRetentionDays} days");
            $this->line("  - Batch size: {$this->batchSize} records");
            $this->line("  - Dry run: " . ($this->dryRun ? 'Yes' : 'No'));
        }
    }
    
    /// <summary>
    /// Display current database statistics
    /// </summary>
    /// <returns>void</returns>
    private function displayDatabaseStats(): void
    {
        $this->info('📊 Current Database Statistics:');
        
        try {
            $metricsCount = Metric::count();
            $metricsSize = $this->getTableSizeMB('metrics');
            $oldMetricsCount = Metric::where('timestamp', '<', Carbon::now()->subDays($this->retentionDays))->count();
            
            $alertsCount = Alert::count();
            $alertsSize = $this->getTableSizeMB('alerts');
            $oldAlertsCount = Alert::where('created_at', '<', Carbon::now()->subDays($this->alertRetentionDays))->count();
            
            $directoryMetricsCount = DirectoryMetric::count();
            $directoryMetricsSize = $this->getTableSizeMB('directory_metrics');
            $oldDirectoryMetricsCount = DirectoryMetric::where('timestamp', '<', Carbon::now()->subDays($this->retentionDays))->count();
            
            $this->table(
                ['Table', 'Total Records', 'Size (MB)', 'Old Records', 'To Delete (%)'],
                [
                    [
                        'metrics',
                        number_format($metricsCount),
                        $metricsSize,
                        number_format($oldMetricsCount),
                        $metricsCount > 0 ? round(($oldMetricsCount / $metricsCount) * 100, 1) . '%' : '0%'
                    ],
                    [
                        'alerts',
                        number_format($alertsCount),
                        $alertsSize,
                        number_format($oldAlertsCount),
                        $alertsCount > 0 ? round(($oldAlertsCount / $alertsCount) * 100, 1) . '%' : '0%'
                    ],
                    [
                        'directory_metrics',
                        number_format($directoryMetricsCount),
                        $directoryMetricsSize,
                        number_format($oldDirectoryMetricsCount),
                        $directoryMetricsCount > 0 ? round(($oldDirectoryMetricsCount / $directoryMetricsCount) * 100, 1) . '%' : '0%'
                    ]
                ]
            );
            
        } catch (\Exception $e) {
            $this->warn('Could not retrieve database statistics: ' . $e->getMessage());
        }
    }
    
    /// <summary>
    /// Clean up old metrics records
    /// </summary>
    /// <returns>void</returns>
    private function cleanupOldMetrics(): void
    {
        $this->info('🔧 Cleaning up old metrics...');
        
        $cutoffDate = Carbon::now()->subDays($this->retentionDays);
        
        if ($this->option('show-details')) {
            $this->line("  Deleting metrics older than: {$cutoffDate->format('Y-m-d H:i:s')}");
        }
        
        $totalToDelete = Metric::where('timestamp', '<', $cutoffDate)->count();
        
        if ($totalToDelete === 0) {
            $this->line("  ✓ No old metrics found to delete");
            return;
        }
        
        if ($this->dryRun) {
            $this->warn("  [DRY RUN] Would delete {$totalToDelete} metrics records");
            return;
        }
        
        $deleted = 0;
        $progressBar = $this->output->createProgressBar($totalToDelete);
        $progressBar->setFormat('  [%bar%] %current%/%max% %percent:3s%% - %memory:6s%');
        
        while (true) {
            $batch = Metric::where('timestamp', '<', $cutoffDate)
                           ->limit($this->batchSize)
                           ->pluck('id');
            
            if ($batch->isEmpty()) {
                break;
            }
            
            $batchDeleted = Metric::whereIn('id', $batch)->delete();
            $deleted += $batchDeleted;
            $this->stats['batches_processed']++;
            
            $progressBar->advance($batchDeleted);
            
            // Small delay to prevent database overload
            usleep(50000); // 50ms
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->stats['metrics_deleted'] = $deleted;
        
        $this->info("  ✅ Deleted {$deleted} metrics records");
        
        Log::info('Metrics cleanup completed', [
            'cutoff_date' => $cutoffDate->toISOString(),
            'records_deleted' => $deleted,
            'batches_processed' => $this->stats['batches_processed']
        ]);
    }
    
    /// <summary>
    /// Clean up old directory metrics records
    /// </summary>
    /// <returns>void</returns>
    private function cleanupOldDirectoryMetrics(): void
    {
        $this->info('📁 Cleaning up old directory metrics...');
        
        $cutoffDate = Carbon::now()->subDays($this->retentionDays);
        
        $totalToDelete = DirectoryMetric::where('timestamp', '<', $cutoffDate)->count();
        
        if ($totalToDelete === 0) {
            $this->line("  ✓ No old directory metrics found to delete");
            return;
        }
        
        if ($this->dryRun) {
            $this->warn("  [DRY RUN] Would delete {$totalToDelete} directory metrics records");
            return;
        }
        
        $deleted = 0;
        $progressBar = $this->output->createProgressBar($totalToDelete);
        $progressBar->setFormat('  [%bar%] %current%/%max% %percent:3s%% - %memory:6s%');
        
        while (true) {
            $batch = DirectoryMetric::where('timestamp', '<', $cutoffDate)
                                   ->limit($this->batchSize)
                                   ->pluck('id');
            
            if ($batch->isEmpty()) {
                break;
            }
            
            $batchDeleted = DirectoryMetric::whereIn('id', $batch)->delete();
            $deleted += $batchDeleted;
            
            $progressBar->advance($batchDeleted);
            
            usleep(50000); // 50ms delay
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->stats['directory_metrics_deleted'] = $deleted;
        
        $this->info("  ✅ Deleted {$deleted} directory metrics records");
    }
    
    /// <summary>
    /// Clean up old resolved/closed alerts
    /// </summary>
    /// <returns>void</returns>
    private function cleanupOldAlerts(): void
    {
        $this->info('🚨 Cleaning up old alerts...');
        
        $cutoffDate = Carbon::now()->subDays($this->alertRetentionDays);
        
        // Only delete resolved/closed alerts, keep active ones regardless of age
        $query = Alert::where('created_at', '<', $cutoffDate)
                     ->whereIn('status', ['Resolved', 'Closed']);
        
        $totalToDelete = $query->count();
        
        if ($totalToDelete === 0) {
            $this->line("  ✓ No old alerts found to delete");
            return;
        }
        
        if ($this->dryRun) {
            $this->warn("  [DRY RUN] Would delete {$totalToDelete} old resolved/closed alerts");
            return;
        }
        
        $deleted = 0;
        $progressBar = $this->output->createProgressBar($totalToDelete);
        $progressBar->setFormat('  [%bar%] %current%/%max% %percent:3s%% - %memory:6s%');
        
        while (true) {
            $batch = Alert::where('created_at', '<', $cutoffDate)
                         ->whereIn('status', ['Resolved', 'Closed'])
                         ->limit($this->batchSize)
                         ->pluck('alert_id');
            
            if ($batch->isEmpty()) {
                break;
            }
            
            $batchDeleted = Alert::whereIn('alert_id', $batch)->delete();
            $deleted += $batchDeleted;
            
            $progressBar->advance($batchDeleted);
            
            usleep(50000); // 50ms delay
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->stats['alerts_deleted'] = $deleted;
        
        $this->info("  ✅ Deleted {$deleted} old alerts (keeping active alerts)");
        
        Log::info('Alerts cleanup completed', [
            'cutoff_date' => $cutoffDate->toISOString(),
            'alerts_deleted' => $deleted
        ]);
    }
    
    /// <summary>
    /// Optimize database tables after cleanup
    /// </summary>
    /// <returns>void</returns>
    private function optimizeTables(): void
    {
        if ($this->dryRun) {
            $this->warn("  [DRY RUN] Would optimize database tables");
            return;
        }
        
        $this->info('⚡ Optimizing database tables...');
        
        $tables = ['metrics', 'alerts', 'directory_metrics'];
        
        foreach ($tables as $table) {
            try {
                if ($this->option('show-details')) {
                    $this->line("  Optimizing table: {$table}");
                }

                // MySQL optimization
                DB::statement("OPTIMIZE TABLE {$table}");

                if ($this->option('show-details')) {
                    $this->line("  ✓ {$table} optimized");
                }
                
            } catch (\Exception $e) {
                $this->warn("  ⚠ Could not optimize table {$table}: " . $e->getMessage());
            }
        }
        
        $this->info("  ✅ Database optimization completed");
    }
    
    /// <summary>
    /// Get table size in MB
    /// </summary>
    /// <param>string $tableName</param>
    /// <returns>string</returns>
    private function getTableSizeMB(string $tableName): string
    {
        try {
            $result = DB::select("
                SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb 
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE() 
                AND table_name = ?
            ", [$tableName]);
            
            return $result[0]->size_mb ?? '0.00';
            
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
    
    /// <summary>
    /// Display cleanup summary
    /// </summary>
    /// <returns>void</returns>
    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('📋 Cleanup Summary:');
        
        $this->table(
            ['Operation', 'Records Processed', 'Status'],
            [
                ['Metrics Deleted', number_format($this->stats['metrics_deleted']), '✅'],
                ['Directory Metrics Deleted', number_format($this->stats['directory_metrics_deleted']), '✅'],
                ['Alerts Deleted', number_format($this->stats['alerts_deleted']), '✅'],
                ['Batches Processed', number_format($this->stats['batches_processed']), '✅'],
                ['Execution Time', $this->stats['execution_time_seconds'] . 's', '⏱️']
            ]
        );
        
        $totalDeleted = $this->stats['metrics_deleted'] + 
                       $this->stats['directory_metrics_deleted'] + 
                       $this->stats['alerts_deleted'];
        
        if ($totalDeleted > 0) {
            $this->info("🎉 Total records deleted: " . number_format($totalDeleted));
            
            if (!$this->dryRun) {
                $this->info("💾 Database performance should be improved");
                $this->line("   Run 'ANALYZE TABLE' on large tables if needed");
            }
        } else {
            $this->info("ℹ️  No cleanup was needed - database is already optimized");
        }
        
        Log::info('MetricsCleanupCommand completed', $this->stats);
    }
    
    #endregion
}