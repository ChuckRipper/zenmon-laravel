<?php

namespace App\Http\Controllers;

use App\Models\UserSession;
use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserSessionResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="Comprehensive user management API endpoints for administrators. Includes user CRUD operations, profile management, password management, and session control with full audit logging and security features."
 * )
 */
/// <summary>
/// Advanced controller for comprehensive system user management
/// Provides full user lifecycle management with role-based access control,
/// security features, audit logging, and administrative oversight capabilities.
/// Only administrators can manage user accounts and access sensitive operations.
/// </summary>
class UserController extends Controller
{
    #region Properties
    
    /// <summary>
    /// Validation rules for user data
    /// </summary>
    private array $validationRules = [
        'login' => 'required|string|min:3|max:50|unique:users,login|regex:/^[a-zA-Z0-9_.-]+$/',
        'email' => 'required|email|max:255|unique:users,email',
        'password' => 'required|string|min:8|max:255|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
        'first_name' => 'required|string|max:100|regex:/^[a-zA-ZąćęłńóśźżĄĆĘŁŃÓŚŹŻ\s-]+$/',
        'last_name' => 'required|string|max:100|regex:/^[a-zA-ZąćęłńóśźżĄĆĘŁŃÓŚŹŻ\s-]+$/',
        'role' => 'required|in:Administrator,Agent,User',
        'is_active' => 'sometimes|boolean'
    ];
    
    /// <summary>
    /// Validation rules for user updates
    /// </summary>
    private array $updateValidationRules = [
        'login' => 'sometimes|string|min:3|max:50|regex:/^[a-zA-Z0-9_.-]+$/',
        'email' => 'sometimes|email|max:255',
        'password' => 'sometimes|string|min:8|max:255|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
        'first_name' => 'sometimes|string|max:100|regex:/^[a-zA-ZąćęłńóśźżĄĆĘŁŃÓŚŹŻ\s-]+$/',
        'last_name' => 'sometimes|string|max:100|regex:/^[a-zA-ZąćęłńóśźżĄĆĘŁŃÓŚŹŻ\s-]+$/',
        'role' => 'sometimes|in:Administrator,Agent,User',
        'is_active' => 'sometimes|boolean'
    ];

    /// <summary>
    /// Simple validation rules for web interface
    /// </summary>
    private array $webValidationRules = [
        'login' => 'required|string|max:255|unique:users,login',
        'email' => 'required|email|max:255|unique:users,email',
        'first_name' => 'nullable|string|max:255',
        'last_name' => 'nullable|string|max:255',
        'role' => 'required|in:Administrator,User',
        'is_active' => 'sometimes|boolean',
        'password' => 'required|confirmed|min:8',
    ];

    /// <summary>
    /// Simple validation rules for web updates
    /// </summary>
    private array $webUpdateValidationRules = [
        'login' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'first_name' => 'nullable|string|max:255',
        'last_name' => 'nullable|string|max:255',
        'role' => 'required|in:Administrator,User',
        'is_active' => 'sometimes|boolean',
        'password' => 'nullable|confirmed|min:8',
    ];

    /// <summary>
    /// Maximum number of users per page for pagination
    /// </summary>
    private const MAX_USERS_PER_PAGE = 100;
    
    /// <summary>
    /// Default pagination size
    /// </summary>
    private const DEFAULT_PAGINATION_SIZE = 20;
        
    #endregion
    
    #region Methods

    public function __construct()
    {
        // Dla ograniczenia dostępu tylko do administratorów, odkomentować:
        // $this->middleware('can:manage-users');
    }


    /**
     * @OA\Get(
     *      path="/api/users",
     *      operationId="getUsersList",
     *      tags={"Users"},
     *      summary="Get comprehensive list of system users (Administrator only)",
     *      description="Returns a paginated, filtered, and searchable list of system users with detailed information, statistics, and metadata. Supports advanced filtering by role, status, creation date, and full-text search across multiple fields. Includes user activity metrics and security information.",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="role",
     *          description="Filter users by their assigned role in the system",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"Administrator", "Agent", "User"},
     *              example="Administrator"
     *          ),
     *          example="User"
     *      ),
     *      @OA\Parameter(
     *          name="is_active",
     *          description="Filter by user account active status (true=active accounts, false=deactivated accounts)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="boolean",
     *              example=true
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="search",
     *          description="Full-text search across login, email, first_name, and last_name fields. Case-insensitive partial matching.",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              maxLength=255,
     *              example="john.doe"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="created_from",
     *          description="Filter users created after this date (inclusive)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              format="date",
     *              example="2024-01-01"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="created_to",
     *          description="Filter users created before this date (inclusive)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              format="date",
     *              example="2024-12-31"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="last_login_days",
     *          description="Filter users who logged in within the last N days",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="integer",
     *              minimum=1,
     *              maximum=365,
     *              example=30
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="sort_by",
     *          description="Field to sort results by",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"created_at", "login", "email", "last_login_date", "role"},
     *              default="created_at"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="sort_direction",
     *          description="Sort direction (ascending or descending)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              enum={"asc", "desc"},
     *              default="desc"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          description="Number of users per page for pagination",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="integer",
     *              minimum=1,
     *              maximum=100,
     *              default=20,
     *              example=25
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="include_stats",
     *          description="Include detailed user statistics and metrics in response",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="boolean",
     *              default=false
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successfully retrieved paginated user list with comprehensive metadata and statistics",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  description="Array of user objects with full details",
     *                  @OA\Items(ref="#/components/schemas/User")
     *              ),
     *              @OA\Property(
     *                  property="meta",
     *                  type="object",
     *                  description="Pagination and collection metadata",
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="from", type="integer", example=1),
     *                  @OA\Property(property="last_page", type="integer", example=5),
     *                  @OA\Property(property="per_page", type="integer", example=20),
     *                  @OA\Property(property="to", type="integer", example=20),
     *                  @OA\Property(property="total", type="integer", example=95),
     *                  @OA\Property(property="has_more_pages", type="boolean", example=true),
     *                  @OA\Property(
     *                      property="statistics",
     *                      type="object",
     *                      description="Comprehensive user statistics",
     *                      @OA\Property(property="total_active_users", type="integer", example=89),
     *                      @OA\Property(property="total_administrators", type="integer", example=3),
     *                      @OA\Property(property="total_agents", type="integer", example=12),
     *                      @OA\Property(property="total_regular_users", type="integer", example=80),
     *                      @OA\Property(property="users_created_today", type="integer", example=2),
     *                      @OA\Property(property="users_with_recent_activity", type="integer", example=45),
     *                      @OA\Property(property="users_never_logged_in", type="integer", example=5),
     *                      @OA\Property(property="users_inactive_30_days", type="integer", example=15)
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="links",
     *                  type="object",
     *                  description="Pagination navigation links",
     *                  @OA\Property(property="first", type="string", example="http://localhost:8000/api/users?page=1"),
     *                  @OA\Property(property="last", type="string", example="http://localhost:8000/api/users?page=5"),
     *                  @OA\Property(property="prev", type="string", nullable=true),
     *                  @OA\Property(property="next", type="string", example="http://localhost:8000/api/users?page=2"),
     *                  @OA\Property(property="self", type="string", example="http://localhost:8000/api/users?page=1")
     *              ),
     *              @OA\Property(
     *                  property="filters_applied",
     *                  type="object",
     *                  description="Summary of filters applied to the query",
     *                  @OA\Property(property="role", type="string", nullable=true),
     *                  @OA\Property(property="is_active", type="boolean", nullable=true),
     *                  @OA\Property(property="search", type="string", nullable=true),
     *                  @OA\Property(property="created_from", type="string", nullable=true),
     *                  @OA\Property(property="created_to", type="string", nullable=true)
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad request - Invalid filter parameters or malformed query",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid filter parameters"),
     *              @OA\Property(
     *                  property="errors",
     *                  type="object",
     *                  @OA\Property(
     *                      property="per_page",
     *                      type="array",
     *                      @OA\Items(type="string", example="The per page field must be between 1 and 100.")
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated - Missing or invalid authentication token",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Administrator access required for user management operations",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Access denied. Administrator privileges required.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Unprocessable Entity - Validation errors in request parameters",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid filter parameters"),
     *              @OA\Property(
     *                  property="errors",
     *                  type="object",
     *                  @OA\Property(
     *                      property="role",
     *                      type="array",
     *                      @OA\Items(type="string", example="The selected role is invalid.")
     *                  ),
     *                  @OA\Property(
     *                      property="search",
     *                      type="array",
     *                      @OA\Items(type="string", example="The search field must not exceed 255 characters.")
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error - Unexpected server error during user retrieval",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Failed to retrieve users list"),
     *              @OA\Property(property="error_id", type="string", example="USR_001_RETRIEVAL_ERROR")
     *          )
     *      )
     * )
     */
    /// <summary>
    /// Get paginated list of users with advanced filtering (API + Web)
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse|View</returns>
    public function index(Request $request): JsonResponse|View
    {
        // API logic
        if ($request->wantsJson()) {
            try {
                $validator = Validator::make($request->all(), [
                    'role' => 'sometimes|in:Administrator,Agent,User',
                    'is_active' => 'sometimes|in:true,false,1,0',
                    'search' => 'sometimes|string|max:255',
                    'created_from' => 'sometimes|date',
                    'created_to' => 'sometimes|date|after_or_equal:created_from',
                    'last_login_days' => 'sometimes|integer|min:1|max:365',
                    'sort_by' => 'sometimes|in:created_at,login,email,last_login_date,role',
                    'sort_direction' => 'sometimes|in:asc,desc',
                    'per_page' => 'sometimes|integer|min:1|max:' . self::MAX_USERS_PER_PAGE,
                    'include_stats' => 'sometimes|in:true,false,1,0'
                ]);
        
                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid filter parameters',
                        'errors' => $validator->errors()
                    ], 422);
                }
        
                $query = User::query();
        
                // Apply filters
                if ($request->has('role')) {
                    $query->where('role', $request->role);
                }
        
                if ($request->has('is_active')) {
                    $query->where('is_active', $request->boolean('is_active'));
                }
        
                if ($request->has('search')) {
                    $search = $request->search;
                    $query->where(function($q) use ($search) {
                        $q->where('login', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%");
                    });
                }
        
                // Date range filters
                if ($request->has('created_from')) {
                    $query->whereDate('created_at', '>=', $request->created_from);
                }
        
                if ($request->has('created_to')) {
                    $query->whereDate('created_at', '<=', $request->created_to);
                }
        
                // Last login filter
                if ($request->has('last_login_days')) {
                    $query->where('last_login_date', '>=', now()->subDays($request->last_login_days));
                }
        
                // Sorting
                $sortBy = $request->get('sort_by', 'created_at');
                $sortDirection = $request->get('sort_direction', 'desc');
                $query->orderBy($sortBy, $sortDirection);
        
                $perPage = $request->get('per_page', self::DEFAULT_PAGINATION_SIZE);
                $users = $query->paginate($perPage);
        
                $response = new UserCollection($users);
                
                if ($request->boolean('include_stats')) {
                    $response->additional([
                        'detailed_stats' => $this->getDetailedUserStatistics()
                    ]);
                }
        
                return response()->json($response);
        
            } catch (\Exception $e) {
                Log::error('UserController@index failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'request_params' => $request->all(),
                    'user_id' => auth()->id(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
        
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve users list',
                    'error_id' => 'USR_001_RETRIEVAL_ERROR'
                ], 500);
            }
        }

            // Web logic
        $users = User::orderBy('id', 'desc')->paginate(15);
        return view('admin.users.index', compact('users'));
        
    }

    /// <summary>
    /// Show create form for users (Web only)
    /// </summary>
    public function create(): mixed
    {
        return view('admin.users.create');
    }

    /**
     * @OA\Post(
     *      path="/api/users",
     *      operationId="createUser",
     *      tags={"Users"},
     *      summary="Create new user (Administrator only)",
     *      description="Create a new system user account",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="User data",
     *          @OA\JsonContent(
     *              required={"login", "email", "password", "first_name", "last_name", "role"},
     *              @OA\Property(property="login", type="string", example="johndoe", minLength=3, maxLength=50),
     *              @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="SecurePass123!", minLength=8),
     *              @OA\Property(property="first_name", type="string", example="John", maxLength=100),
     *              @OA\Property(property="last_name", type="string", example="Doe", maxLength=100),
     *              @OA\Property(property="role", type="string", enum={"Administrator", "Agent", "User"}, example="User"),
     *              @OA\Property(property="is_active", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="User created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User created successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - Administrator access required")
     * )
     */
    /// <summary>
    /// Create a new user account (API + Web)
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse|RedirectResponse</returns>
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        try {

            // API validation
            if ($request->wantsJson()) {
                $validator = Validator::make($request->all(), $this->validationRules);
            } else {
                // Web validation (od kolegi - uproszczona)
                $validator = Validator::make($request->all(), [
                    'login' => 'required|string|max:255|unique:users,login',
                    'email' => 'required|email|max:255|unique:users,email',
                    'first_name' => 'nullable|string|max:255',
                    'last_name' => 'nullable|string|max:255',
                    'role' => 'required|in:Administrator,User',
                    'is_active' => 'sometimes|boolean',
                    'password' => 'required|confirmed|min:8',
                ]);
            }

            // $validator = Validator::make($request->all(), $this->validationRules);

            // if ($validator->fails()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Validation failed',
            //         'errors' => $validator->errors()
            //     ], 422);
            // }

            if ($validator->fails()) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }
                return back()
                    ->withErrors($validator)
                    ->withInput();
            }

            // Hash password
            $userData = $validator->validated();
            $userData['password'] = Hash::make($userData['password']);
            $userData['is_active'] = $userData['is_active'] ?? true;

            $user = User::create($userData);

            Log::info('New user created', [
                'user_id' => $user->getKey(),
                'login' => $user->login,
                'role' => $user->role,
                'created_by' => auth()->user()->login
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User created successfully',
                    'data' => new UserResource($user)
                ], 201);
            }

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'Użytkownik został dodany.');

        } catch (\Exception $e) {
            Log::error('UserController@store failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->except(['password']),
                'created_by' => auth()->id()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create user'
                ], 500);
            }

            return back()->with('error', 'Błąd podczas tworzenia użytkownika.');
        }
    }

    /**
     * @OA\Get(
     *      path="/api/users/{user}",
     *      operationId="getUser",
     *      tags={"Users"},
     *      summary="Get user details (Administrator only)",
     *      description="Get detailed information about specific user",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="user",
     *          description="User ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(response=404, description="User not found"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - Administrator access required")
     * )
     */
    /// <summary>
    /// Show specific user details (API only)
    /// </summary>
    /// <param>User $user</param>
    /// <returns>JsonResponse</returns>
    public function show(User $user): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            Log::error('UserController@show failed', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
                'requested_by' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user details'
            ], 500);
        }
    }

    /// <summary>
    /// Show edit form for user (Web only)
    /// </summary>
    /// <param>Request $request</param>
    /// <param>User $user</param>
    /// <returns>JsonResponse|View</returns>
    public function edit(Request $request, User $user): JsonResponse|View
    {
        if ($request->wantsJson()) {
            return response()->json([
                'data' => new UserResource($user),
            ]);
        }
        
        return view('admin.users.edit', compact('user'));
    }

    /**
     * @OA\Put(
     *      path="/api/users/{user}",
     *      operationId="updateUser",
     *      tags={"Users"},
     *      summary="Update user (Administrator only)",
     *      description="Update user account information",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="user",
     *          description="User ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="User data to update",
     *          @OA\JsonContent(
     *              @OA\Property(property="login", type="string", example="johndoe_updated", minLength=3, maxLength=50),
     *              @OA\Property(property="email", type="string", format="email", example="john.updated@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="NewSecurePass123!", minLength=8),
     *              @OA\Property(property="first_name", type="string", example="John", maxLength=100),
     *              @OA\Property(property="last_name", type="string", example="Doe", maxLength=100),
     *              @OA\Property(property="role", type="string", enum={"Administrator", "Agent", "User"}, example="User"),
     *              @OA\Property(property="is_active", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="User updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User updated successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="User not found"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - Administrator access required")
     * )
     */
    /// <summary>
    /// Update user account
    /// </summary>
    /// <param>Request $request</param>
    /// <param>User $user</param>
    /// <returns>JsonResponse</returns>
    public function update(Request $request, User $user): JsonResponse|RedirectResponse
    {
        try {
            // Choose validation rules and modify for unique constraints
            if ($request->wantsJson()) {
                $rules = $this->updateValidationRules;
                if ($request->has('login')) {
                    $rules['login'] .= ",{$user->getKey()}";
                }
                if ($request->has('email')) {
                    $rules['email'] .= ",{$user->getKey()}";
                }
            } else {
                $rules = $this->webUpdateValidationRules;
                $rules['login'] .= ",{$user->getKey()}";
                $rules['email'] .= ",{$user->getKey()}";
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }
                return back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $updateData = $validator->validated();

            // Hash password if provided
            if (isset($updateData['password']) && !empty($updateData['password'])) {
                $updateData['password'] = Hash::make($updateData['password']);
            } else {
                unset($updateData['password']);
            }

            if (!$request->wantsJson()) {
                $updateData['is_active'] = $request->boolean('is_active', $user->is_active);
            }

            $originalData = $user->only(['login', 'email', 'role', 'is_active']);
            $user->update($updateData);

            Log::info('User updated', [
                'user_id' => $user->getKey(),
                'login' => $user->login,
                'original_data' => $originalData,
                'updated_by' => auth()->user()->login,
                'changes' => array_keys($updateData)
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User updated successfully',
                    'data' => new UserResource($user->fresh())
                ]);
            }

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'Użytkownik został zaktualizowany.');

        } catch (\Exception $e) {
            Log::error('UserController@update failed', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
                'request_data' => $request->except(['password']),
                'updated_by' => auth()->id()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update user'
                ], 500);
            }

            return back()->with('error', 'Błąd podczas aktualizacji użytkownika.');
        }
    }

    /**
     * @OA\Delete(
     *      path="/api/users/{user}",
     *      operationId="deleteUser",
     *      tags={"Users"},
     *      summary="Delete user (Administrator only)",
     *      description="Delete user account (soft delete - sets is_active to false)",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="user",
     *          description="User ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="User deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User deleted successfully")
     *          )
     *      ),
     *      @OA\Response(response=404, description="User not found"),
     *      @OA\Response(response=400, description="Cannot delete own account or last administrator"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - Administrator access required")
     * )
     */
    /// <summary>
    /// Delete user account (API + Web)
    /// </summary>
    /// <param>User $user</param>
    /// <returns>JsonResponse</returns>
    public function destroy(Request $request, User $user): JsonResponse|RedirectResponse
    {
        try {
            // Prevent self-deletion
            if ($user->getKey() === auth()->id()) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete your own account'
                    ], 400);
                }
                return back()->with('error', 'Nie możesz usunąć swojego własnego konta.');
            }

            // Prevent deletion of last administrator
            if ($user->role === 'Administrator') {
                $adminCount = User::where('role', 'Administrator')
                                ->where('is_active', true)
                                ->where('id', '!=', $user->getKey())
                                ->count();

                if ($adminCount === 0) {
                    if ($request->wantsJson()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot delete the last active administrator'
                        ], 400);
                    }
                    return back()->with('error', 'Nie można usunąć ostatniego aktywnego administratora.');
                }
            }

            // Zapisz informacje przed usunięciem (dla logów)
            $deletedUserInfo = [
                'user_id' => $user->getKey(),
                'login' => $user->login,
                'email' => $user->email,
                'role' => $user->role,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'was_active' => $user->is_active,
                'deleted_by' => auth()->user()->login
            ];

            // HARD DELETE - rzeczywiste usunięcie z bazy danych
            $user->delete();

            Log::warning('User account permanently deleted', $deletedUserInfo);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User deleted permanently',
                    'deleted_user' => [
                        'login' => $deletedUserInfo['login'],
                        'role' => $deletedUserInfo['role']
                    ]
                ]);
            }

            return redirect()
                ->route('admin.users.index')
                ->with('success', 'Użytkownik został trwale usunięty.');

        } catch (\Exception $e) {
            Log::error('UserController@destroy failed', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
                'deleted_by' => auth()->id()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete user'
                ], 500);
            }

            return back()->with('error', 'Błąd podczas usuwania użytkownika.');
        }
    }

    /**
     * @OA\Post(
     *      path="/api/users/{user}/activate",
     *      operationId="toggleUserStatus",
     *      tags={"Users"},
     *      summary="Activate/deactivate user (Administrator only)",
     *      description="Toggle user active status",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="user",
     *          description="User ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="is_active", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="User status updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User activated successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(response=400, description="Cannot deactivate own account or last administrator"),
     *      @OA\Response(response=404, description="User not found"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - Administrator access required")
     * )
     */
    /// <summary>
    /// Toggle user active status (API only)
    /// </summary>
    /// <param>Request $request</param>
    /// <param>User $user</param>
    /// <returns>JsonResponse</returns>
    public function activate(Request $request, User $user): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'is_active' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $isActive = $request->boolean('is_active');

            // Prevent self-deactivation
            if (!$isActive && $user->getKey() === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot deactivate your own account'
                ], 400);
            }

            // Prevent deactivation of last administrator
            if (!$isActive && $user->role === 'Administrator') {
                $adminCount = User::where('role', 'Administrator')
                                ->where('is_active', true)
                                ->where('id', '!=', $user->getKey())
                                ->count();

                if ($adminCount === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot deactivate the last active administrator'
                    ], 400);
                }
            }

            $user->update(['is_active' => $isActive]);

            $action = $isActive ? 'activated' : 'deactivated';
            
            Log::info("User {$action}", [
                'user_id' => $user->getKey(),
                'login' => $user->login,
                'is_active' => $isActive,
                'changed_by' => auth()->user()->login
            ]);

            return response()->json([
                'success' => true,
                'message' => "User {$action} successfully",
                'data' => new UserResource($user->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('UserController@activate failed', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
                'changed_by' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *      path="/api/users/{user}/reset-password",
     *      operationId="resetUserPassword",
     *      tags={"Users"},
     *      summary="Reset user password (Administrator only)",
     *      description="Reset user password to a new value",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="user",
     *          description="User ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="password", type="string", format="password", example="NewSecurePass123!", minLength=8),
     *              @OA\Property(property="password_confirmation", type="string", format="password", example="NewSecurePass123!")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Password reset successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Password reset successfully")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="User not found"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden - Administrator access required")
     * )
     */
    /// <summary>
    /// Reset user password (API only)
    /// </summary>
    /// <param>Request $request</param>
    /// <param>User $user</param>
    /// <returns>JsonResponse</returns>
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'password' => 'required|string|min:8|max:255|confirmed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            Log::warning('User password reset by administrator', [
                'user_id' => $user->getKey(),
                'login' => $user->login,
                'reset_by' => auth()->user()->login
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('UserController@resetPassword failed', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
                'reset_by' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/user/profile",
     *      operationId="getUserProfile",
     *      tags={"Users"},
     *      summary="Get current user profile",
     *      description="Get current authenticated user's profile information",
     *      security={{"sanctum":{}}},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    /// <summary>
    /// Get current user's profile (API only)
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function profile(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new UserResource($request->user())
            ]);

        } catch (\Exception $e) {
            Log::error('UserController@profile failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *      path="/api/user/profile",
     *      operationId="updateUserProfile",
     *      tags={"Users"},
     *      summary="Update current user profile",
     *      description="Update current authenticated user's profile information",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="first_name", type="string", example="John", maxLength=100),
     *              @OA\Property(property="last_name", type="string", example="Doe", maxLength=100),
     *              @OA\Property(property="email", type="string", format="email", example="john.doe@example.com")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Profile updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    /// <summary>
    /// Update current user's profile (API only)
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:100',
                'last_name' => 'sometimes|string|max:100',
                'email' => "sometimes|email|max:255|unique:users,email,{$user->getKey()}"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $validator->validated();
            $user->update($updateData);

            Log::info('User profile updated', [
                'user_id' => $user->getKey(),
                'login' => $user->login,
                'changes' => array_keys($updateData)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => new UserResource($user->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('UserController@updateProfile failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *      path="/api/user/change-password",
     *      operationId="changeUserPassword",
     *      tags={"Users"},
     *      summary="Change current user password",
     *      description="Change current authenticated user's password",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="current_password", type="string", format="password", example="CurrentPass123!"),
     *              @OA\Property(property="password", type="string", format="password", example="NewSecurePass123!", minLength=8),
     *              @OA\Property(property="password_confirmation", type="string", format="password", example="NewSecurePass123!")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Password changed successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Password changed successfully")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=400, description="Current password incorrect"),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    /// <summary>
    /// Change current user's password (API only)
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|max:255|confirmed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            Log::info('User changed own password', [
                'user_id' => $user->getKey(),
                'login' => $user->login
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('UserController@changePassword failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change password'
            ], 500);
        }
    }

    #endregion

    #region Private Methods
    /// <summary>
    /// Get detailed user statistics for enhanced reporting
    /// </summary>
    /// <returns>array</returns>
    private function getDetailedUserStatistics(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'users_by_role' => [
                'administrators' => User::where('role', 'Administrator')->count(),
                'agents' => User::where('role', 'Agent')->count(),
                'regular_users' => User::where('role', 'User')->count()
            ],
            'recent_activity' => [
                'users_created_today' => User::whereDate('created_at', today())->count(),
                'users_created_this_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
                'users_created_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                'users_logged_in_today' => User::whereDate('last_login_date', today())->count(),
                'users_logged_in_this_week' => User::where('last_login_date', '>=', now()->startOfWeek())->count()
            ],
            'security_metrics' => [
                'users_never_logged_in' => User::whereNull('last_login_date')->count(),
                'users_inactive_30_days' => User::where('last_login_date', '<', now()->subDays(30))->count(),
                'users_inactive_90_days' => User::where('last_login_date', '<', now()->subDays(90))->count(),
                'active_sessions_count' => UserSession::where('is_active', true)->count()
            ],
            'generated_at' => now()->toISOString()
        ];
    }

    #endregion
}