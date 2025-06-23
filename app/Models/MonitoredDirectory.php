<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *      schema="MonitoredDirectory",
 *      type="object",
 *      title="MonitoredDirectory",
 *      description="Directory being monitored for disk usage",
 *      @OA\Property(property="directory_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="directory_path", type="string", example="/var/log"),
 *      @OA\Property(property="is_active", type="boolean", example=true),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(
 *          property="host",
 *          type="object",
 *          @OA\Property(property="host_id", type="integer"),
 *          @OA\Property(property="host_name", type="string"),
 *          @OA\Property(property="ip_address", type="string")
 *      )
 * )
 */
class MonitoredDirectory extends Model
{
    use HasFactory;

    #region Properties
    protected $primaryKey = 'directory_id';
    
    protected $fillable = [
        'host_id',
        'directory_path',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];
    #endregion

    #region Relationships
    public function host()
    {
        return $this->belongsTo(Host::class, 'host_id', 'host_id');
    }

    public function directoryMetrics()
    {
        return $this->hasMany(DirectoryMetric::class, 'directory_id', 'directory_id');
    }

    public function latestMetric()
    {
        return $this->hasOne(DirectoryMetric::class, 'directory_id', 'directory_id')
                   ->latest('timestamp');
    }
    #endregion

    #region Scopes
    /// <summary>
    /// Scope for active directories
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /// <summary>
    /// Scope for Linux root directories
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeLinuxRoot($query)
    {
        return $query->whereIn('directory_path', ['/root', '/var', '/tmp']);
    }
    #endregion

    #region Methods
    /// <summary>
    /// Check if directory is Linux system directory
    /// </summary>
    /// <returns>bool</returns>
    public function isLinuxSystemDirectory(): bool
    {
        return in_array($this->directory_path, ['/root', '/var', '/tmp', '/usr', '/opt']);
    }

    /// <summary>
    /// Check if directory is Windows system directory
    /// </summary>
    /// <returns>bool</returns>
    public function isWindowsSystemDirectory(): bool
    {
        return str_starts_with(strtolower($this->directory_path), 'c:\\');
    }

    /// <summary>
    /// Get directory name without path
    /// </summary>
    /// <returns>string</returns>
    public function getDirectoryName(): string
    {
        return basename($this->directory_path);
    }

    /// <summary>
    /// Get latest usage percentage
    /// </summary>
    /// <returns>float|null</returns>
    public function getLatestUsagePercentage(): ?float
    {
        $metric = $this->latestMetric;
        if (!$metric || $metric->total_space == 0) {
            return null;
        }
        return ($metric->used_space / $metric->total_space) * 100;
    }
    #endregion
}