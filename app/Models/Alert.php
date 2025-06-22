<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *      schema="Alert",
 *      type="object",
 *      title="Alert",
 *      description="Alert model",
 *      @OA\Property(property="alert_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="metric_type_id", type="integer", example=1),
 *      @OA\Property(property="alert_level", type="string", enum={"Warning", "Critical"}, example="Critical"),
 *      @OA\Property(property="status", type="string", enum={"Active", "Acknowledged", "Resolved"}, example="Active"),
 *      @OA\Property(property="alert_message", type="string", example="CPU usage exceeded critical threshold"),
 *      @OA\Property(property="current_value", type="number", format="float", example=95.5),
 *      @OA\Property(property="threshold_value", type="number", format="float", example=90.0),
 *      @OA\Property(property="created_date", type="string", format="date-time", example="2025-06-22T10:30:00Z"),
 *      @OA\Property(property="acknowledged_date", type="string", format="date-time", nullable=true),
 *      @OA\Property(property="acknowledged_by", type="integer", nullable=true),
 *      @OA\Property(property="resolved_date", type="string", format="date-time", nullable=true),
 *      @OA\Property(
 *          property="host",
 *          type="object",
 *          @OA\Property(property="host_id", type="integer"),
 *          @OA\Property(property="host_name", type="string"),
 *          @OA\Property(property="ip_address", type="string")
 *      ),
 *      @OA\Property(
 *          property="metric_type",
 *          type="object",
 *          @OA\Property(property="metric_type_id", type="integer"),
 *          @OA\Property(property="metric_name", type="string"),
 *          @OA\Property(property="unit", type="string")
 *      )
 * )
 */
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
