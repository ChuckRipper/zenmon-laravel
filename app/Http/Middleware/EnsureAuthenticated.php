<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/// <summary>
/// Middleware to ensure proper API authentication with detailed error responses
/// </summary>
class EnsureAuthenticated
{
    #region Methods

    /// <summary>
    /// Handle an incoming request and verify authentication
    /// </summary>
    /// <param name="Request">$request</param>
    /// <param name="Closure">$next</param>
    /// <returns>Response</returns>
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated via Sanctum
        if (!auth('sanctum')->check()) {
            return $this->unauthorizedResponse($request);
        }

        // Check if user account is active
        $user = auth('sanctum')->user();
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Account disabled',
                'error' => 'User account has been deactivated',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        return $next($request);
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Return standardized unauthorized response
    /// </summary>
    /// <param name="Request">$request</param>
    /// <returns>Response</returns>
    private function unauthorizedResponse(Request $request): Response
    {
        $authHeader = $request->header('Authorization');
        
        // Determine specific error type
        if (!$authHeader) {
            $error = 'Missing Authorization header';
            $hint = 'Include Authorization: Bearer {token} header';
        } elseif (!str_starts_with($authHeader, 'Bearer ')) {
            $error = 'Invalid Authorization header format';
            $hint = 'Use format: Authorization: Bearer {token}';
        } else {
            $error = 'Invalid or expired token';
            $hint = 'Obtain new token via POST /api/login';
        }

        return response()->json([
            'message' => 'Unauthenticated',
            'error' => $error,
            'hint' => $hint,
            'endpoints' => [
                'login' => 'POST /api/login',
                'public_health' => 'GET /api/public/health'
            ],
            'timestamp' => now()->toISOString()
        ], 401);
    }

    #endregion
}