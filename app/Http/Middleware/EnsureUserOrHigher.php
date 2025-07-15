<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/// <summary>
/// Middleware to ensure user has User role or higher (User, Agent, Administrator)
/// </summary>
class EnsureUserOrHigher
{
    #region Methods

    /// <summary>
    /// Handle an incoming request and verify user privileges
    /// </summary>
    /// <param name="Request">$request</param>
    /// <param name="Closure">$next</param>
    /// <returns>Response</returns>
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure user is authenticated first
        if (!auth('sanctum')->check()) {
            \Log::warning('Unauthenticated access attempt', [
                'operation' => $request->route()->getName() ?? $request->method() . ' ' . $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'Authentication required for this operation',
                'hint' => 'Obtain token via POST /api/login',
                'operation' => $request->route()->getName() ?? 'unknown',
                'timestamp' => now()->toISOString()
            ], 401);
        }

        $user = auth('sanctum')->user();

        // Check if user has valid role (User, Agent, or Administrator)
        // $validRoles = ['User', 'Agent', 'Administrator'];
        $validRoles = ['User', 'Administrator'];
        if (!in_array($user->role, $validRoles)) {
            \Log::warning('Invalid user role access attempt', [
                'operation' => $request->route()->getName() ?? $request->method() . ' ' . $request->path(),
                'user_id' => $user->id,
                'user_login' => $user->login,
                'user_role' => $user->role,
                'valid_roles' => $validRoles,
                'ip_address' => $request->ip(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'message' => 'Forbidden',
                'error' => 'Invalid user role for this operation',
                'user_role' => $user->role,
                'valid_roles' => $validRoles,
                'operation' => $request->route()->getName() ?? 'unknown',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        // Check if user account is active
        if (!$user->is_active) {
            \Log::warning('Inactive user access attempt', [
                'operation' => $request->route()->getName() ?? $request->method() . ' ' . $request->path(),
                'user_id' => $user->id,
                'user_login' => $user->login,
                'user_role' => $user->role,
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'message' => 'Forbidden',
                'error' => 'User account has been deactivated',
                'operation' => $request->route()->getName() ?? 'unknown',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        return $next($request);
    }

    #endregion
}