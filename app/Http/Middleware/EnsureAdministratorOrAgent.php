<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/// <summary>
/// Middleware to ensure user has Administrator OR Agent role (hierarchical access)
/// Administrator can access everything that Agent can access
/// </summary>
class EnsureAdministratorOrAgent
{
    #region Methods

    /// <summary>
    /// Handle an incoming request and verify administrator or agent privileges
    /// </summary>
    /// <param name="Request">$request</param>
    /// <param name="Closure">$next</param>
    /// <returns>Response</returns>
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure user is authenticated first
        if (!auth('sanctum')->check()) {
            \Log::warning('Unauthenticated access attempt to admin/agent endpoint', [
                'operation' => $request->route()->getName() ?? $request->method() . ' ' . $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'Administrator or Agent access requires authentication',
                'hint' => 'Obtain token via POST /api/login',
                'timestamp' => now()->toISOString()
            ], 401);
        }

        $user = auth('sanctum')->user();

        // Check if user has administrator OR agent role (hierarchical)
        $allowedRoles = ['Administrator', 'Agent'];
        if (!in_array($user->role, $allowedRoles)) {
            \Log::warning('Insufficient privileges for admin/agent endpoint', [
                'operation' => $request->route()->getName() ?? $request->method() . ' ' . $request->path(),
                'user_id' => $user->id,
                'user_login' => $user->login,
                'user_role' => $user->role,
                'allowed_roles' => $allowedRoles,
                'ip_address' => $request->ip(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'message' => 'Forbidden',
                'error' => 'Administrator or Agent privileges required for this operation',
                'user_role' => $user->role,
                'allowed_roles' => $allowedRoles,
                'operation' => $request->route()->getName() ?? 'unknown',
                'hint' => 'This endpoint requires Administrator or Agent role',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        // Check if account is active
        if (!$user->is_active) {
            \Log::warning('Inactive user access attempt to admin/agent endpoint', [
                'operation' => $request->route()->getName() ?? $request->method() . ' ' . $request->path(),
                'user_id' => $user->id,
                'user_login' => $user->login,
                'user_role' => $user->role,
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'message' => 'Forbidden',
                'error' => 'Account has been deactivated',
                'operation' => $request->route()->getName() ?? 'unknown',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        return $next($request);
    }
}
