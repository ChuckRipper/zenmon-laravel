<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Host extends Model
{
    use HasFactory;

    #region Properties
    protected $primaryKey = 'host_id';
    
    protected $fillable = [
        'host_name',
        'ip_address', 
        'description',
        'operating_system',
        'agent_version',
        'is_active',
        'last_contact_date'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_contact_date' => 'datetime'
    ];
    #endregion

    #region Relationships
    public function configuration()
    {
        return $this->hasOne(HostConfiguration::class, 'host_id', 'host_id');
    }

    public function metrics()
    {
        return $this->hasMany(Metric::class, 'host_id', 'host_id');
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class, 'host_id', 'host_id');
    }

    public function connectionStatuses()
    {
        return $this->hasMany(ConnectionStatus::class, 'host_id', 'host_id');
    }

    public function monitoredDirectories()
    {
        return $this->hasMany(MonitoredDirectory::class, 'host_id', 'host_id');
    }

    public function alertThresholds()
    {
        return $this->hasMany(AlertThreshold::class, 'host_id', 'host_id');
    }
    #endregion
}
