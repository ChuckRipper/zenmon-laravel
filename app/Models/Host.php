<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *      schema="Host",
 *      type="object",
 *      title="Host",
 *      description="Monitored host/server",
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="host_name", type="string", example="web-server-01"),
 *      @OA\Property(property="ip_address", type="string", example="192.168.1.100"),
 *      @OA\Property(property="description", type="string", example="Production web server"),
 *      @OA\Property(property="operating_system", type="string", example="Ubuntu 22.04"),
 *      @OA\Property(property="agent_version", type="string", example="1.0.0"),
 *      @OA\Property(property="is_active", type="boolean", example=true),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(property="last_contact_date", type="string", format="date-time", nullable=true)
 * )
 */
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
