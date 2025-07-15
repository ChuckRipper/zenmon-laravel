<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/// <summary>
/// Middleware to ensure only agents can access agent-specific endpoints
/// </summary>
class EnsureAgent
{
    #region Methods

    /// <summary>
    /// Handle an incoming request and verify agent privileges
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
                'error' => 'Agent access requires authentication',
                'hint' => 'Obtain token via POST /api/login with Agent credentials',
                'timestamp' => now()->toISOString()
            ], 401);
        }

        $user = auth('sanctum')->user();

        // Check if user has agent role
        if ($user->role !== 'Agent') {
            \Log::warning('Non-agent access attempt to agent endpoint', [
                'operation' => $request->route()->getName() ?? $request->method() . ' ' . $request->path(),
                'user_id' => $user->id,
                'user_login' => $user->login,
                'user_role' => $user->role,
                'required_role' => 'Agent',
                'ip_address' => $request->ip(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'message' => 'Forbidden',
                'error' => 'Agent privileges required for this operation',
                'user_role' => $user->role,
                'required_role' => 'Agent',
                'operation' => $request->route()->getName() ?? 'unknown',
                'hint' => 'This endpoint is restricted to Agent accounts only',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        // Check if agent account is active
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Forbidden',
                'error' => 'Agent account has been deactivated',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        return $next($request);
    }
}
