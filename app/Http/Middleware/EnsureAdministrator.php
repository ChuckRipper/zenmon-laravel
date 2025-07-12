<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/// <summary>
/// Middleware to ensure only administrators can access restricted endpoints
/// </summary>
class EnsureAdministrator
{
    #region Methods

    /// <summary>
    /// Handle an incoming request and verify administrator privileges
    /// </summary>
    /// <param name="Request">$request</param>
    /// <param name="Closure">$next</param>
    /// <returns>Response</returns>
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure user is authenticated first
        if (!auth('sanctum')->check()) {
            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'Administrator access requires authentication',
                'hint' => 'Obtain token via POST /api/login',
                'timestamp' => now()->toISOString()
            ], 401);
        }

        $user = auth('sanctum')->user();

        // Check if user has administrator role
        if ($user->role !== 'Administrator') {
            return response()->json([
                'message' => 'Forbidden',
                'error' => 'Administrator privileges required',
                'user_role' => $user->role,
                'required_role' => 'Administrator',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        // Check if administrator account is active
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Forbidden',
                'error' => 'Administrator account has been deactivated',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        return $next($request);
    }

    #endregion
}