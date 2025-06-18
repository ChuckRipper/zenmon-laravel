<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HostConfiguration extends Model
{
    use HasFactory;

    #region Properties
    protected $primaryKey = 'configuration_id';
    
    protected $fillable = [
        'host_id',
        'data_collection_interval',
        'enable_cpu_monitoring',
        'enable_ram_monitoring',
        'enable_disk_monitoring',
        'enable_network_monitoring',
        'updated_by_user_id'
    ];

    protected $casts = [
        'enable_cpu_monitoring' => 'boolean',
        'enable_ram_monitoring' => 'boolean',
        'enable_disk_monitoring' => 'boolean',
        'enable_network_monitoring' => 'boolean'
    ];
    #endregion

    #region Relationships
    public function host()
    {
        return $this->belongsTo(Host::class, 'host_id', 'host_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id', 'id');
    }
    #endregion

    #region Methods
    /// <summary>
    /// Check if all monitoring is enabled
    /// </summary>
    /// <returns>bool</returns>
    public function isFullMonitoringEnabled(): bool
    {
        return $this->enable_cpu_monitoring && 
               $this->enable_ram_monitoring && 
               $this->enable_disk_monitoring && 
               $this->enable_network_monitoring;
    }

    /// <summary>
    /// Get collection interval in minutes
    /// </summary>
    /// <returns>float</returns>
    public function getIntervalInMinutes(): float
    {
        return $this->data_collection_interval / 60;
    }

    /// <summary>
    /// Get enabled monitoring types
    /// </summary>
    /// <returns>array</returns>
    public function getEnabledMonitoringTypes(): array
    {
        $types = [];
        if ($this->enable_cpu_monitoring) $types[] = 'CPU';
        if ($this->enable_ram_monitoring) $types[] = 'RAM';
        if ($this->enable_disk_monitoring) $types[] = 'Disk';
        if ($this->enable_network_monitoring) $types[] = 'Network';
        return $types;
    }
    #endregion
}
