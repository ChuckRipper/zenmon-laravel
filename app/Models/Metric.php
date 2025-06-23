<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *      schema="Metric",
 *      type="object",
 *      title="Metric",
 *      description="Metric data point",
 *      @OA\Property(property="metric_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="metric_type_id", type="integer", example=1),
 *      @OA\Property(property="value", type="number", format="float", example=85.5),
 *      @OA\Property(property="timestamp", type="string", format="date-time", example="2025-06-22T10:30:00Z"),
 *      @OA\Property(property="additional_info", type="object", example={"process_count": 45}),
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
 *          @OA\Property(property="unit", type="string"),
 *          @OA\Property(property="description", type="string")
 *      )
 * )
 */
class Metric extends Model
{
    use HasFactory;

    #region Properties
    protected $primaryKey = 'metric_id';
    
    protected $fillable = [
        'host_id',
        'metric_type_id',
        'value',
        'timestamp',
        'additional_info'
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'timestamp' => 'datetime',
        'additional_info' => 'array'
    ];

    public $timestamps = false; // Używamy własnego timestamp
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
    #endregion

    #region Scopes
    /// <summary>
    /// Scope for getting metrics from last N hours
    /// </summary>
    /// <param>$query</param>
    /// <param>int $hours</param>
    /// <returns>mixed</returns>
    public function scopeLastHours($query, int $hours = 24)
    {
        return $query->where('timestamp', '>=', now()->subHours($hours));
    }

    /// <summary>
    /// Scope for getting latest metrics per host and metric type
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeLatest($query)
    {
        return $query->orderBy('timestamp', 'desc');
    }

    /// <summary>
    /// Scope for CPU metrics
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeCpu($query)
    {
        return $query->whereHas('metricType', function($q) {
            $q->where('metric_name', 'CPU');
        });
    }
    #endregion

    #region Methods
    /// <summary>
    /// Check if metric value exceeds threshold
    /// </summary>
    /// <param>float $threshold</param>
    /// <returns>bool</returns>
    public function exceedsThreshold(float $threshold): bool
    {
        return $this->value > $threshold;
    }
    #endregion
}