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
 *      @OA\Property(property="status", type="string", enum={"Active", "Acknowledged", "Closed", "Resolved"}, example="Active"),
 *      @OA\Property(property="alert_message", type="string", example="CPU usage exceeded critical threshold"),
 *      @OA\Property(property="current_value", type="number", format="float", example=95.5),
 *      @OA\Property(property="threshold_value", type="number", format="float", example=90.0),
 *      @OA\Property(property="created_at", type="string", format="date-time", example="2025-06-22T10:30:00Z"),
 *      @OA\Property(property="acknowledged_date", type="string", format="date-time", nullable=true),
 *      @OA\Property(property="acknowledged_by_user_id", type="integer", nullable=true),
 *      @OA\Property(property="closed_date", type="string", format="date-time", nullable=true),
 *      @OA\Property(property="closed_by_user_id", type="integer", nullable=true),
 *      @OA\Property(property="close_comment", type="string", nullable=true),
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

    #region Constants
    
    /// <summary>
    /// Alert level constants
    /// </summary>
    public const LEVEL_WARNING = 'Warning';
    public const LEVEL_CRITICAL = 'Critical';
    
    /// <summary>
    /// Alert status constants
    /// </summary>
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_ACKNOWLEDGED = 'Acknowledged';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_RESOLVED = 'Resolved';
    
    #endregion

    #region Properties
    // <summary>
    /// Primary key for the alerts table
    /// </summary>
    protected $primaryKey = 'alert_id';
    
    /// <summary>
    /// Mass assignable attributes
    /// </summary>
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

    /// <summary>
    /// Attribute casting
    /// </summary>
    protected $casts = [
        'current_value' => 'decimal:4',
        'threshold_value' => 'decimal:4',
        'acknowledged_date' => 'datetime',
        'closed_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    #endregion

    #region Relationships
    /// <summary>
    /// Alert belongs to a host
    /// </summary>
    /// <returns>BelongsTo</returns>
    public function host()
    {
        return $this->belongsTo(Host::class, 'host_id', 'host_id');
    }

    /// <summary>
    /// Alert belongs to a metric type
    /// </summary>
    /// <returns>BelongsTo</returns>
    public function metricType()
    {
        return $this->belongsTo(MetricType::class, 'metric_type_id', 'metric_type_id');
    }

    /// <summary>
    /// Alert acknowledged by user
    /// </summary>
    /// <returns>BelongsTo</returns>
    public function acknowledgedByUser()
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id', 'id');
    }

    /// <summary>
    /// Alert closed by user
    /// </summary>
    /// <returns>BelongsTo</returns>
    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id', 'id');
    }
    #endregion

    #region Scopes
    
    /// <summary>
    /// Scope for active alerts
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /// <summary>
    /// Scope for acknowledged alerts
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeAcknowledged($query)
    {
        return $query->where('status', self::STATUS_ACKNOWLEDGED);
    }

    /// <summary>
    /// Scope for closed alerts
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /// <summary>
    /// Scope for resolved alerts
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeResolved($query)
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    /// <summary>
    /// Scope for active or acknowledged alerts (unresolved)
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeActiveOrAcknowledged($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_ACKNOWLEDGED]);
    }

    /// <summary>
    /// Scope for unresolved alerts (Active or Acknowledged)
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_ACKNOWLEDGED]);
    }

    /// <summary>
    /// Scope for resolved alerts (Closed or Resolved)
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeResolutionComplete($query)
    {
        return $query->whereIn('status', [self::STATUS_CLOSED, self::STATUS_RESOLVED]);
    }

    /// <summary>
    /// Scope for critical alerts
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeCritical($query)
    {
        return $query->where('alert_level', self::LEVEL_CRITICAL);
    }

    /// <summary>
    /// Scope for warning alerts
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeWarning($query)
    {
        return $query->where('alert_level', self::LEVEL_WARNING);
    }

    /// <summary>
    /// Scope for alerts by host
    /// </summary>
    /// <param>Builder $query</param>
    /// <param>int $hostId</param>
    /// <returns>Builder</returns>
    public function scopeByHost($query, int $hostId)
    {
        return $query->where('host_id', $hostId);
    }

    /// <summary>
    /// Scope for recent alerts (last 24 hours)
    /// </summary>
    /// <param>Builder $query</param>
    /// <returns>Builder</returns>
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }
    
    #endregion

    #region Methods
    
    /// <summary>
    /// Check if alert is active
    /// </summary>
    /// <returns>bool</returns>
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /// <summary>
    /// Check if alert is acknowledged
    /// </summary>
    /// <returns>bool</returns>
    public function isAcknowledged(): bool
    {
        return $this->status === self::STATUS_ACKNOWLEDGED;
    }

    /// <summary>
    /// Check if alert is closed
    /// </summary>
    /// <returns>bool</returns>
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /// <summary>
    /// Check if alert is resolved
    /// </summary>
    /// <returns>bool</returns>
    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /// <summary>
    /// Check if alert is fully resolved (closed or resolved)
    /// </summary>
    /// <returns>bool</returns>
    public function isFullyResolved(): bool
    {
        return in_array($this->status, [self::STATUS_CLOSED, self::STATUS_RESOLVED]);
    }

    /// <summary>
    /// Check if alert is critical
    /// </summary>
    /// <returns>bool</returns>
    public function isCritical(): bool
    {
        return $this->alert_level === self::LEVEL_CRITICAL;
    }

    /// <summary>
    /// Check if alert is warning
    /// </summary>
    /// <returns>bool</returns>
    public function isWarning(): bool
    {
        return $this->alert_level === self::LEVEL_WARNING;
    }

    /// <summary>
    /// Get time since alert was created in minutes
    /// </summary>
    /// <returns>int</returns>
    public function getActiveTimeMinutes(): int
    {
        return $this->created_at->diffInMinutes(now());
    }

    /// <summary>
    /// Get alert age in human readable format
    /// </summary>
    /// <returns>string</returns>
    public function getAgeForHumans(): string
    {
        return $this->created_at->diffForHumans();
    }

    /// <summary>
    /// Acknowledge the alert
    /// </summary>
    /// <param>int $userId</param>
    /// <returns>bool</returns>
    public function acknowledge(int $userId): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $this->status = self::STATUS_ACKNOWLEDGED;
        $this->acknowledged_date = now();
        $this->acknowledged_by_user_id = $userId;
        
        return $this->save();
    }

    /// <summary>
    /// Close the alert
    /// </summary>
    /// <param>int $userId</param>
    /// <param>string $comment</param>
    /// <returns>bool</returns>
    public function close(int $userId, string $comment = null): bool
    {
        if ($this->status === self::STATUS_CLOSED) {
            return false;
        }

        $this->status = self::STATUS_CLOSED;
        $this->closed_date = now();
        $this->closed_by_user_id = $userId;
        if ($comment) {
            $this->close_comment = $comment;
        }
        
        return $this->save();
    }

    /// <summary>
    /// Resolve the alert (automatically when condition returns to normal)
    /// </summary>
    /// <returns>bool</returns>
    public function resolve(): bool
    {
        if ($this->status === self::STATUS_RESOLVED) {
            return false;
        }

        $this->status = self::STATUS_RESOLVED;
        $this->closed_date = now(); // Using closed_date for resolved timestamp too
        
        return $this->save();
    }

    /// <summary>
    /// Get the route key for the model
    /// </summary>
    /// <returns>string</returns>
    public function getRouteKeyName(): string
    {
        return 'alert_id';
    }
    
    #endregion
}