<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *      schema="AlertThreshold",
 *      type="object",
 *      title="AlertThreshold",
 *      description="Alert threshold configuration",
 *      @OA\Property(property="alert_threshold_id", type="integer", example=1),
 *      @OA\Property(property="metric_type_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", nullable=true, example=1, description="NULL for global thresholds"),
 *      @OA\Property(property="warning_threshold", type="number", format="float", example=70.0),
 *      @OA\Property(property="critical_threshold", type="number", format="float", example=90.0),
 *      @OA\Property(property="is_active", type="boolean", example=true),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time"),
 *      @OA\Property(
 *          property="metric_type",
 *          type="object",
 *          @OA\Property(property="metric_type_id", type="integer"),
 *          @OA\Property(property="metric_name", type="string"),
 *          @OA\Property(property="unit", type="string")
 *      ),
 *      @OA\Property(
 *          property="host",
 *          type="object",
 *          nullable=true,
 *          @OA\Property(property="host_id", type="integer"),
 *          @OA\Property(property="host_name", type="string")
 *      )
 * )
 */
class AlertThreshold extends Model
{
    use HasFactory;

    #region Properties
    protected $primaryKey = 'threshold_id';
    
    protected $fillable = [
        'host_id',
        'metric_type_id',
        'warning_threshold',
        'critical_threshold',
        'is_active',
        'created_by_user_id'
    ];

    protected $casts = [
        'warning_threshold' => 'decimal:4',
        'critical_threshold' => 'decimal:4',
        'is_active' => 'boolean'
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

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }
    #endregion

    #region Scopes
    /// <summary>
    /// Scope for global thresholds (not specific to host)
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeGlobal($query)
    {
        return $query->whereNull('host_id');
    }

    /// <summary>
    /// Scope for host-specific thresholds
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeHostSpecific($query)
    {
        return $query->whereNotNull('host_id');
    }

    /// <summary>
    /// Scope for active thresholds
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    #endregion

    #region Methods
    /// <summary>
    /// Check if threshold is global
    /// </summary>
    /// <returns>bool</returns>
    public function isGlobal(): bool
    {
        return is_null($this->host_id);
    }

    /// <summary>
    /// Check if value exceeds warning threshold
    /// </summary>
    /// <param>float $value</param>
    /// <returns>bool</returns>
    public function exceedsWarning(float $value): bool
    {
        return $value >= $this->warning_threshold;
    }

    /// <summary>
    /// Check if value exceeds critical threshold
    /// </summary>
    /// <param>float $value</param>
    /// <returns>bool</returns>
    public function exceedsCritical(float $value): bool
    {
        return $value >= $this->critical_threshold;
    }

    /// <summary>
    /// Get alert level for given value
    /// </summary>
    /// <param>float $value</param>
    /// <returns>string|null</returns>
    public function getAlertLevel(float $value): ?string
    {
        if ($this->exceedsCritical($value)) {
            return 'Critical';
        } elseif ($this->exceedsWarning($value)) {
            return 'Warning';
        }
        return null;
    }
    #endregion
}
