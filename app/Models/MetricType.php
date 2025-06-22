<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *      schema="MetricType",
 *      type="object",
 *      title="MetricType",
 *      description="Type of metric (CPU, RAM, Disk, Network)",
 *      @OA\Property(property="metric_type_id", type="integer", example=1),
 *      @OA\Property(property="metric_name", type="string", example="CPU"),
 *      @OA\Property(property="unit", type="string", example="%"),
 *      @OA\Property(property="description", type="string", example="CPU usage percentage"),
 *      @OA\Property(property="created_at", type="string", format="date-time"),
 *      @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class MetricType extends Model
{
    use HasFactory;

    #region Properties
    protected $primaryKey = 'metric_type_id';
    
    protected $fillable = [
        'metric_name',
        'unit',
        'description'
    ];
    #endregion

    #region Relationships
    public function metrics()
    {
        return $this->hasMany(Metric::class, 'metric_type_id', 'metric_type_id');
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class, 'metric_type_id', 'metric_type_id');
    }

    public function alertThresholds()
    {
        return $this->hasMany(AlertThreshold::class, 'metric_type_id', 'metric_type_id');
    }
    #endregion

    #region Methods
    /// <summary>
    /// Check if this metric type is CPU
    /// </summary>
    /// <returns>bool</returns>
    public function isCpu(): bool
    {
        return $this->metric_name === 'CPU';
    }

    /// <summary>
    /// Check if this metric type is RAM
    /// </summary>
    /// <returns>bool</returns>
    public function isRam(): bool
    {
        return $this->metric_name === 'RAM';
    }

    /// <summary>
    /// Check if this metric type is Disk
    /// </summary>
    /// <returns>bool</returns>
    public function isDisk(): bool
    {
        return $this->metric_name === 'Disk';
    }
    #endregion
}
