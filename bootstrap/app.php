<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        
        // Register custom middleware aliases
        $middleware->alias([
        'auth.secure' => \App\Http\Middleware\EnsureAuthenticated::class,
        'admin' => \App\Http\Middleware\EnsureAdministrator::class,
        'agent' => \App\Http\Middleware\EnsureAgent::class,
        'admin.or.agent' => \App\Http\Middleware\EnsureAdministratorOrAgent::class,
        'user' => \App\Http\Middleware\EnsureUserOrHigher::class,
        'user+' => \App\Http\Middleware\EnsureUserOrHigher::class, // Alias dla routes
        ]);

        // Global middleware for all requests
        $middleware->append([
            // Add any global middleware here if needed
        ]);

        // API middleware group customization
        $middleware->group('api', [
            // Throttle requests to prevent abuse
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':60,1',
            
            // Handle CORS for API requests
            \Illuminate\Http\Middleware\HandleCors::class,
            
            // Ensure proper JSON responses
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        // Web middleware group (for future web interface)
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        
        // Custom exception handling for API
        // $exceptions->respond(function (\Illuminate\Http\Response $response, \Throwable $exception, \Illuminate\Http\Request $request) { //Wywala błędy 500 na wielu endpointach API
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, \Illuminate\Http\Request $request) {
        // $exceptions->respond(function ($response, \Throwable $exception, \Illuminate\Http\Request $request) {
            
            // Handle authentication exceptions with custom response
            if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'error' => 'Valid authentication token required',
                    'hint' => 'Obtain token via POST /api/login',
                    'timestamp' => now()->toISOString()
                ], 401);
            }

            // Handle authorization exceptions
            if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return response()->json([
                    'message' => 'Forbidden',
                    'error' => 'Insufficient privileges for this operation',
                    'timestamp' => now()->toISOString()
                ], 403);
            }

            // Handle validation exceptions with detailed response
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $exception->errors(),
                    'timestamp' => now()->toISOString()
                ], 422);
            }

            // Handle model not found exceptions
            if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'message' => 'Resource not found',
                    'error' => 'The requested resource does not exist',
                    'timestamp' => now()->toISOString()
                ], 404);
            }

            // Handle rate limiting
            if ($exception instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                return response()->json([
                    'message' => 'Too many requests',
                    'error' => 'Rate limit exceeded',
                    'retry_after' => $exception->getHeaders()['Retry-After'] ?? 60,
                    'timestamp' => now()->toISOString()
                ], 429);
            }

            // Default exception handling for production
            if (app()->environment('production')) {
                return response()->json([
                    'message' => 'Server error',
                    'error' => 'An unexpected error occurred',
                    'timestamp' => now()->toISOString()
                ], 500);
            }

            // Return original response for development
            return $response;
        });

    })->create();