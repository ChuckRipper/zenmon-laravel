<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    #region Properties
    protected $primaryKey = 'alert_id';
    
    protected $fillable = [
        'host_id',
        'metric_type_id',
        'alert_level',
        'alert_message',
        'current_value',
        'threshold_value',
        'status',
        'acknowledged_date',
        'acknowledged_by_user_id',
        'closed_date',
        'closed_by_user_id',
        'close_comment'
    ];

    protected $casts = [
        'current_value' => 'decimal:4',
        'threshold_value' => 'decimal:4',
        'acknowledged_date' => 'datetime',
        'closed_date' => 'datetime'
    ];
    #endregion

    #region Relationships
    public function host()
    {
        return $this->belongsTo(Host::class, 'host_id', 'host_id');
    }

    public function metricType()
    {
        return $this->belongsTo(MetricType::class, 'metric_type_id', 'metric_type_id');
    }

    public function acknowledgedByUser()
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id', 'id');
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id', 'id');
    }
    #endregion

    #region Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'Acknowledged');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'Closed');
    }

    public function scopeCritical($query)
    {
        return $query->where('alert_level', 'Critical');
    }
    #endregion
}
