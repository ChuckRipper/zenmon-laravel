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
        // Check if the request is for API or JSON response
        $isApiRequest = $request->is('api/*') || $request->wantsJson();
        
        // Ensure user is authenticated first
         if (!auth($isApiRequest ? 'sanctum' : 'web')->check()) {
            if ($isApiRequest) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'error' => 'Administrator access requires authentication',
                    'hint' => 'Obtain token via POST /api/login',
                    'timestamp' => now()->toISOString()
                ], 401);
            } else {
                return redirect()->route('login');
            }
        }

        $user = auth($isApiRequest ? 'sanctum' : 'web')->user();

        // Check if user has administrator role
        if ($user->role !== 'Administrator') {
            if ($isApiRequest) {
                return response()->json([
                    'message' => 'Forbidden',
                    'error' => 'Administrator privileges required',
                    'user_role' => $user->role,
                    'required_role' => 'Administrator',
                    'timestamp' => now()->toISOString()
                ], 403);
            } else {
                return response()->view('errors.403', [], 403);
            }
        }

        // Check if administrator account is active
        if (!$user->is_active) {
            if ($isApiRequest) {
                return response()->json([
                    'message' => 'Forbidden',
                    'error' => 'Administrator account has been deactivated',
                    'timestamp' => now()->toISOString()
                ], 403);
            } else {
                return redirect()->route('dashboard')->with('error', 'Konto dezaktywowane.');
            }
        }

        return $next($request);
    }

    #endregion
}