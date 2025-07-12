<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *      schema="DirectoryMetric",
 *      type="object",
 *      title="DirectoryMetric",
 *      description="Directory disk usage metrics",
 *      @OA\Property(property="directory_metric_id", type="integer", example=1),
 *      @OA\Property(property="directory_id", type="integer", example=1),
 *      @OA\Property(property="used_space", type="integer", example=1073741824, description="Used space in bytes"),
 *      @OA\Property(property="total_space", type="integer", example=10737418240, description="Total space in bytes"),
 *      @OA\Property(property="available_space", type="integer", example=9663676416, description="Available space in bytes"),
 *      @OA\Property(property="file_count", type="integer", example=1542),
 *      @OA\Property(property="timestamp", type="string", format="date-time"),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(
 *          property="monitored_directory",
 *          type="object",
 *          @OA\Property(property="directory_id", type="integer"),
 *          @OA\Property(property="directory_path", type="string"),
 *          @OA\Property(property="host_name", type="string")
 *      )
 * )
 */
class DirectoryMetric extends Model
{
    use HasFactory;

    #region Properties
    // NAPRAWKA: Explicite wskaż tabelę i pobieraj wszystkie kolumny
    protected $table = 'directory_metrics';
    protected $primaryKey = 'directory_metric_id';
    // protected $guarded = []; // Pozwól na wszystkie kolumny
    
    protected $fillable = [
        'directory_id',
        'used_space',
        'total_space',
        'available_space',
        'file_count',
        'timestamp'
    ];

    protected $casts = [
        'timestamp' => 'datetime'
    ];

    public $timestamps = false; // Używamy własnego timestamp
    #endregion

    #region Relationships
    public function monitoredDirectory()
    {
        return $this->belongsTo(MonitoredDirectory::class, 'directory_id', 'directory_id');
    }
    #endregion

    #region Scopes
    /// <summary>
    /// Scope for metrics from last N hours
    /// </summary>
    /// <param>$query</param>
    /// <param>int $hours</param>
    /// <returns>mixed</returns>
    public function scopeLastHours($query, int $hours = 24)
    {
        return $query->where('timestamp', '>=', now()->subHours($hours));
    }

    /// <summary>
    /// Scope for latest metrics
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeLatest($query)
    {
        return $query->orderBy('timestamp', 'desc');
    }
    #endregion

    #region Methods
    /// <summary>
    /// Get usage percentage
    /// </summary>
    /// <returns>float</returns>
    public function getUsagePercentage(): float
    {
        if ($this->total_space == 0) {
            return 0;
        }
        return ($this->used_space / $this->total_space) * 100;
    }

    /// <summary>
    /// Get formatted used space
    /// </summary>
    /// <returns>string</returns>
    public function getFormattedUsedSpace(): string
    {
        return $this->formatBytes($this->used_space);
    }

    /// <summary>
    /// Get formatted total space
    /// </summary>
    /// <returns>string</returns>
    public function getFormattedTotalSpace(): string
    {
        return $this->formatBytes($this->total_space);
    }

    /// <summary>
    /// Get formatted available space
    /// </summary>
    /// <returns>string</returns>
    public function getFormattedAvailableSpace(): string
    {
        return $this->formatBytes($this->available_space);
    }

    /// <summary>
    /// Format bytes to human readable format
    /// </summary>
    /// <param>int $bytes</param>
    /// <returns>string</returns>
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /// <summary>
    /// Check if directory usage is critical (over threshold)
    /// </summary>
    /// <param>float $threshold</param>
    /// <returns>bool</returns>
    public function isCriticalUsage(float $threshold = 90.0): bool
    {
        return $this->getUsagePercentage() >= $threshold;
    }
    #endregion
}