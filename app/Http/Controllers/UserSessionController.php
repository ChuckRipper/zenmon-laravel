<?php

namespace App\Http\Controllers;

use App\Models\UserSession;
use App\Models\User;
use App\Http\Resources\UserSessionResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="User Sessions",
 *     description="API Endpoints for managing user login sessions"
 * )
 */
class UserSessionController extends Controller
{
    #region Properties
    /// <summary>
    /// Default pagination size
    /// </summary>
    protected int $perPage = 25;
    #endregion

    #region Methods

    /**
     * @OA\Get(
     *      path="/api/user-sessions",
     *      operationId="getUserSessionsList",
     *      tags={"User Sessions"},
     *      summary="Get list of user sessions",
     *      description="Returns paginated list of user sessions with filtering options",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="user_id",
     *          description="Filter by user ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="is_active",
     *          description="Filter by active status",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="hours",
     *          description="Get sessions from last N hours",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", minimum=1, maximum=720)
     *      ),
     *      @OA\Parameter(
     *          name="expired_only",
     *          description="Show only expired sessions",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="ip_address",
     *          description="Filter by IP address",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Number of items per page",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", minimum=1, maximum=100)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="List of user sessions",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UserSession")),
     *              @OA\Property(property="meta", type="object",
     *                  @OA\Property(property="current_page", type="integer"),
     *                  @OA\Property(property="total", type="integer"),
     *                  @OA\Property(property="per_page", type="integer"),
     *                  @OA\Property(property="last_page", type="integer")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthorized")
     * )
     */
    /// <summary>
    /// Display a listing of user sessions with filtering
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>AnonymousResourceCollection</returns>
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = UserSession::with(['user']);

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Recent sessions filter
        if ($request->filled('hours')) {
            $hours = min($request->hours, 720); // Max 30 days
            $query->recent($hours);
        }

        // Expired sessions only
        if ($request->filled('expired_only') && filter_var($request->expired_only, FILTER_VALIDATE_BOOLEAN)) {
            $query->expired();
        }

        // Filter by IP address
        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'LIKE', '%' . $request->ip_address . '%');
        }

        $perPage = min($request->get('per_page', $this->perPage), 100);
        
        $sessions = $query->orderBy('last_activity_date', 'desc')
                         ->orderBy('login_date', 'desc')
                         ->paginate($perPage);

        return UserSessionResource::collection($sessions)
            ->additional([
                'meta' => [
                    'total_active_sessions' => UserSession::active()->count(),
                    'total_expired_sessions' => UserSession::expired()->count(),
                    'sessions_last_24h' => UserSession::recent(24)->count(),
                    'unique_users_online' => UserSession::active()->distinct('user_id')->count(),
                    'unique_ip_addresses' => UserSession::active()->distinct('ip_address')->count()
                ]
            ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

     /**
     * @OA\Post(
     *      path="/api/user-sessions",
     *      operationId="createUserSession",
     *      tags={"User Sessions"},
     *      summary="Create new user session",
     *      description="Creates a new user session (login)",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"user_id","ip_address"},
     *              @OA\Property(property="user_id", type="integer", example=1),
     *              @OA\Property(property="ip_address", type="string", example="192.168.1.100"),
     *              @OA\Property(property="user_agent", type="string", example="Mozilla/5.0...")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Session created successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/UserSession"),
     *              @OA\Property(property="session_token", type="string"),
     *              @OA\Property(property="message", type="string", example="Session created successfully")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="User not found")
     * )
     */
    /// <summary>
    /// Store a newly created user session
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'ip_address' => 'required|ip',
                'user_agent' => 'sometimes|string|max:500'
            ]);

            // Check if user is active
            $user = User::find($validated['user_id']);
            if (!$user->isActive()) {
                return response()->json([
                    'message' => 'User account is inactive'
                ], 403);
            }

            // Generate unique session token
            $sessionToken = Str::random(64);
            
            // Terminate other active sessions for this user (optional, based on business logic)
            // UserSession::where('user_id', $validated['user_id'])->active()->update(['is_active' => false]);

            $session = UserSession::create([
                'user_id' => $validated['user_id'],
                'session_token' => hash('sha256', $sessionToken), // Store hashed token
                'login_date' => now(),
                'last_activity_date' => now(),
                'ip_address' => $validated['ip_address'],
                'user_agent' => $validated['user_agent'] ?? $request->userAgent(),
                'is_active' => true
            ]);

            // Update user's last login date
            $user->update(['last_login_date' => now()]);

            $session->load(['user']);

            return response()->json([
                'data' => new UserSessionResource($session),
                'session_token' => $sessionToken, // Return unhashed token for client
                'message' => 'Session created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/user-sessions/{id}",
     *      operationId="getUserSession",
     *      tags={"User Sessions"},
     *      summary="Get user session details",
     *      description="Returns specific user session with activity details",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Session ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Session details",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/UserSession")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Session not found")
     * )
     */
    /// <summary>
    /// Display the specified user session
    /// </summary>
    /// <param>UserSession $userSession</param>
    /// <returns>JsonResponse</returns>
    public function show(UserSession $userSession): JsonResponse
    {
        $userSession->load(['user']);

        return response()->json([
            'data' => new UserSessionResource($userSession)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UserSession $userSession)
    {
        //
    }

    /**
     * @OA\Put(
     *      path="/api/user-sessions/{id}",
     *      operationId="updateUserSession",
     *      tags={"User Sessions"},
     *      summary="Update user session activity",
     *      description="Updates session last activity timestamp",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Session ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=false,
     *          @OA\JsonContent(
     *              @OA\Property(property="update_activity", type="boolean", example=true, description="Whether to update last activity"),
     *              @OA\Property(property="is_active", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Session updated successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/UserSession"),
     *              @OA\Property(property="message", type="string", example="Session updated successfully")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Session not found")
     * )
     */
    /// <summary>
    /// Update the specified user session
    /// </summary>
    /// <param>Request $request</param>
    /// <param>UserSession $userSession</param>
    /// <returns>JsonResponse</returns>
    public function update(Request $request, UserSession $userSession): JsonResponse
    {
        try {
            $validated = $request->validate([
                'update_activity' => 'sometimes|boolean',
                'is_active' => 'sometimes|boolean'
            ]);

            $updateData = [];

            // Update activity timestamp
            if (isset($validated['update_activity']) && $validated['update_activity']) {
                $updateData['last_activity_date'] = now();
            }

            // Update active status
            if (isset($validated['is_active'])) {
                $updateData['is_active'] = $validated['is_active'];
            }

            if (!empty($updateData)) {
                $userSession->update($updateData);
            }

            $userSession->load(['user']);

            return response()->json([
                'data' => new UserSessionResource($userSession),
                'message' => 'Session updated successfully'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * @OA\Delete(
     *      path="/api/user-sessions/{id}",
     *      operationId="deleteUserSession",
     *      tags={"User Sessions"},
     *      summary="Delete user session",
     *      description="Deletes/terminates user session",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Session ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=204,
     *          description="Session deleted successfully"
     *      ),
     *      @OA\Response(response=404, description="Session not found")
     * )
     */
    /// <summary>
    /// Remove the specified user session
    /// </summary>
    /// <param>UserSession $userSession</param>
    /// <returns>JsonResponse</returns>
    public function destroy(UserSession $userSession): JsonResponse
    {
        $userSession->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *      path="/api/user-sessions/active",
     *      operationId="getActiveUserSessions",
     *      tags={"User Sessions"},
     *      summary="Get active user sessions",
     *      description="Returns list of currently active user sessions",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="limit",
     *          description="Limit number of results",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer", minimum=1, maximum=200)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Active sessions",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UserSession")),
     *              @OA\Property(property="meta", type="object",
     *                  @OA\Property(property="total_active", type="integer"),
     *                  @OA\Property(property="unique_users", type="integer"),
     *                  @OA\Property(property="unique_ips", type="integer")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthorized")
     * )
     */
    /// <summary>
    /// Get all currently active user sessions
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function getActiveSessions(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 50), 200);
        
        $activeSessions = UserSession::with(['user'])
                                   ->active()
                                   ->orderBy('last_activity_date', 'desc')
                                   ->limit($limit)
                                   ->get();

        return response()->json([
            'data' => UserSessionResource::collection($activeSessions),
            'meta' => [
                'total_active' => UserSession::active()->count(),
                'unique_users' => UserSession::active()->distinct('user_id')->count(),
                'unique_ips' => UserSession::active()->distinct('ip_address')->count(),
                'sessions_last_hour' => UserSession::active()
                                                 ->where('last_activity_date', '>=', now()->subHour())
                                                 ->count(),
                'longest_session_hours' => $this->getLongestActiveSessionHours()
            ]
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/user-sessions/{session}/terminate",
     *      operationId="terminateUserSession",
     *      tags={"User Sessions"},
     *      summary="Terminate user session",
     *      description="Terminates specific user session",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="session",
     *          description="Session ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=false,
     *          @OA\JsonContent(
     *              @OA\Property(property="reason", type="string", example="Administrative termination")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Session terminated successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/UserSession"),
     *              @OA\Property(property="message", type="string", example="Session terminated successfully")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Session not found"),
     *      @OA\Response(response=409, description="Session already terminated")
     * )
     */
    /// <summary>
    /// Terminate specific user session
    /// </summary>
    /// <param>Request $request</param>
    /// <param>UserSession $session</param>
    /// <returns>JsonResponse</returns>
    public function terminateSession(Request $request, UserSession $session): JsonResponse
    {
        if (!$session->is_active) {
            return response()->json([
                'message' => 'Session is already terminated'
            ], 409);
        }

        $session->terminate();
        $session->load(['user']);

        return response()->json([
            'data' => new UserSessionResource($session),
            'message' => 'Session terminated successfully'
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/user-sessions/cleanup-expired",
     *      operationId="cleanupExpiredSessions",
     *      tags={"User Sessions"},
     *      summary="Cleanup expired sessions",
     *      description="Removes or marks as inactive expired user sessions",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=false,
     *          @OA\JsonContent(
     *              @OA\Property(property="hours_threshold", type="integer", example=8, description="Sessions inactive for N hours"),
     *              @OA\Property(property="delete_old", type="boolean", example=false, description="Delete sessions older than 30 days")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Cleanup completed",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="terminated_sessions", type="integer"),
     *              @OA\Property(property="deleted_sessions", type="integer"),
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthorized")
     * )
     */
    /// <summary>
    /// Cleanup expired user sessions
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function cleanupExpiredSessions(Request $request): JsonResponse
    {
        $hoursThreshold = $request->get('hours_threshold', 8);
        $deleteOld = filter_var($request->get('delete_old', false), FILTER_VALIDATE_BOOLEAN);

        // Terminate expired active sessions
        $terminatedCount = UserSession::active()
                                    ->expired($hoursThreshold)
                                    ->update(['is_active' => false]);

        $deletedCount = 0;
        if ($deleteOld) {
            // Delete sessions older than 30 days
            $deletedCount = UserSession::where('login_date', '<', now()->subDays(30))
                                     ->delete();
        }

        return response()->json([
            'terminated_sessions' => $terminatedCount,
            'deleted_sessions' => $deletedCount,
            'message' => "Cleanup completed. Terminated: {$terminatedCount}, Deleted: {$deletedCount}"
        ]);
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get longest active session duration in hours
    /// </summary>
    /// <returns>int</returns>
    private function getLongestActiveSessionHours(): int
    {
        $longestSession = UserSession::active()
                                   ->orderBy('login_date', 'asc')
                                   ->first();

        if (!$longestSession) {
            return 0;
        }

        // return $longestSession->login_date->diffInHours(now());
        return $longestSession->login_date ? $longestSession->login_date->diffInHours(now()) : 0;
    }

    #endregion
}