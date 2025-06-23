<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *      schema="ConnectionStatus",
 *      type="object",
 *      title="ConnectionStatus",
 *      description="Host connection status",
 *      @OA\Property(property="connection_status_id", type="integer", example=1),
 *      @OA\Property(property="host_id", type="integer", example=1),
 *      @OA\Property(property="status", type="string", enum={"Online", "Offline", "Unknown"}, example="Online"),
 *      @OA\Property(property="last_check_date", type="string", format="date-time"),
 *      @OA\Property(property="error_message", type="string", nullable=true, example=null),
 *      @OA\Property(property="response_time_ms", type="integer", nullable=true, example=145),
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
class ConnectionStatus extends Model
{
    use HasFactory;

    #region Properties
    protected $primaryKey = 'status_id';
    
    protected $fillable = [
        'host_id',
        'status',
        'response_time',
        'last_check_date',
        'error_message'
    ];

    protected $casts = [
        'last_check_date' => 'datetime'
    ];

    public $timestamps = false; // Używamy własnego last_check_date
    #endregion

    #region Relationships
    public function host()
    {
        return $this->belongsTo(Host::class, 'host_id', 'host_id');
    }
    #endregion

    #region Scopes
    /// <summary>
    /// Scope for online hosts
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeOnline($query)
    {
        return $query->where('status', 'Online');
    }

    /// <summary>
    /// Scope for offline hosts
    /// </summary>
    /// <param>$query</param>
    /// <returns>mixed</returns>
    public function scopeOffline($query)
    {
        return $query->where('status', 'Offline');
    }

    /// <summary>
    /// Scope for recent checks (last N minutes)
    /// </summary>
    /// <param>$query</param>
    /// <param>int $minutes</param>
    /// <returns>mixed</returns>
    public function scopeRecentChecks($query, int $minutes = 5)
    {
        return $query->where('last_check_date', '>=', now()->subMinutes($minutes));
    }
    #endregion

    #region Methods
    /// <summary>
    /// Check if host is online
    /// </summary>
    /// <returns>bool</returns>
    public function isOnline(): bool
    {
        return $this->status === 'Online';
    }

    /// <summary>
    /// Check if host is offline
    /// </summary>
    /// <returns>bool</returns>
    public function isOffline(): bool
    {
        return $this->status === 'Offline';
    }

    /// <summary>
    /// Check if response time is slow (over threshold)
    /// </summary>
    /// <param>int $thresholdMs</param>
    /// <returns>bool</returns>
    public function isSlowResponse(int $thresholdMs = 1000): bool
    {
        return $this->response_time !== null && $this->response_time > $thresholdMs;
    }

    /// <summary>
    /// Get formatted response time
    /// </summary>
    /// <returns>string</returns>
    public function getFormattedResponseTime(): string
    {
        if ($this->response_time === null) {
            return 'N/A';
        }
        return $this->response_time . ' ms';
    }
    #endregion
}