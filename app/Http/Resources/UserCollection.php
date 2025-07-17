<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/// <summary>
/// Collection resource for paginated User data
/// Provides formatted collection with metadata and statistics
/// </summary>
class UserCollection extends ResourceCollection
{
    #region Properties

    /// <summary>
    /// Resource class to use for individual items
    /// </summary>
    public $collects = UserResource::class;

    #endregion

    #region Methods

    /// <summary>
    /// Transform the resource collection into an array
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>array<string, mixed></returns>
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'current_page' => $this->currentPage(),
                'from' => $this->firstItem(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'to' => $this->lastItem(),
                'total' => $this->total(),
                'has_more_pages' => $this->hasMorePages(),
                
                // Additional statistics
                'statistics' => [
                    'total_active_users' => $this->getTotalActiveUsers(),
                    'total_administrators' => $this->getTotalAdministrators(),
                    'total_agents' => $this->getTotalAgents(),
                    'total_regular_users' => $this->getTotalRegularUsers(),
                    'users_created_today' => $this->getUsersCreatedToday(),
                    'users_with_recent_activity' => $this->getUsersWithRecentActivity()
                ]
            ],
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
                'self' => $this->url($this->currentPage())
            ]
        ];
    }

    /// <summary>
    /// Get total count of active users
    /// </summary>
    /// <returns>int</returns>
    private function getTotalActiveUsers(): int
    {
        return $this->collection->where('is_active', true)->count();
    }

    /// <summary>
    /// Get total count of administrators
    /// </summary>
    /// <returns>int</returns>
    private function getTotalAdministrators(): int
    {
        return $this->collection->where('role', 'Administrator')->count();
    }

    /// <summary>
    /// Get total count of agents
    /// </summary>
    /// <returns>int</returns>
    private function getTotalAgents(): int
    {
        return $this->collection->where('role', 'Agent')->count();
    }

    /// <summary>
    /// Get total count of regular users
    /// </summary>
    /// <returns>int</returns>
    private function getTotalRegularUsers(): int
    {
        return $this->collection->where('role', 'User')->count();
    }

    /// <summary>
    /// Get count of users created today
    /// </summary>
    /// <returns>int</returns>
    private function getUsersCreatedToday(): int
    {
        return $this->collection->filter(function ($user) {
            return $user->created_at && $user->created_at->isToday();
        })->count();
    }

    /// <summary>
    /// Get count of users with recent activity (last 24 hours)
    /// </summary>
    /// <returns>int</returns>
    private function getUsersWithRecentActivity(): int
    {
        return $this->collection->filter(function ($user) {
            return $user->last_login_date && $user->last_login_date->isAfter(now()->subDay());
        })->count();
    }

    /// <summary>
    /// Add custom headers to the response
    /// </summary>
    /// <param>Request $request</param>
    /// <param>$response</param>
    /// <returns>void</returns>
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Type', 'UserCollection');
        $response->header('X-Total-Count', $this->total());
    }

    #endregion
}