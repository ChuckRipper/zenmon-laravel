<?php

namespace App\Http\Controllers;

use App\Models\MonitoredDirectory;
use App\Models\Host;
use App\Http\Resources\MonitoredDirectoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Monitored Directories",
 *     description="API Endpoints for managing monitored directories"
 * )
 */
class MonitoredDirectoryController extends Controller
{
    #region Properties
    
    /// <summary>
    /// Validation rules for monitored directory data
    /// </summary>
    // private array $validationRules = [
    //     'host_id' => 'required|integer|exists:hosts,host_id',
    //     'directory_path' => 'required|string|max:500',
    //     'is_active' => 'sometimes|boolean'
    // ];

    /// <summary>
    /// Default pagination size
    /// </summary>
    protected int $perPage = 20;

    #endregion
    
    #region Methods

     /**
     * @OA\Get(
     *      path="/api/monitored-directories",
     *      operationId="getMonitoredDirectoriesList",
     *      tags={"Monitored Directories"},
     *      summary="Get list of monitored directories",
     *      description="Returns paginated list of monitored directories with filtering options",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host_id",
     *          description="Filter by host ID",
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
     *          name="search",
     *          description="Search in directory paths",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="linux_only",
     *          description="Show only Linux system directories",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="boolean")
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
     *          description="List of monitored directories",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MonitoredDirectory")),
     *              @OA\Property(property="meta", type="object",
     *                  @OA\Property(property="current_page", type="integer"),
     *                  @OA\Property(property="total", type="integer"),
     *                  @OA\Property(property="per_page", type="integer"),
     *                  @OA\Property(property="last_page", type="integer"),
     *                  @OA\Property(property="has_more", type="boolean")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthorized")
     * )
     */
    /// <summary>
    /// Display a listing of monitored directories with filtering and pagination
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>AnonymousResourceCollection</returns>
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MonitoredDirectory::with(['host', 'latestMetric']);

        // Filter by host
        if ($request->filled('host_id')) {
            $query->where('host_id', $request->host_id);
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // Search in directory paths
        if ($request->filled('search')) {
            $query->where('directory_path', 'LIKE', '%' . $request->search . '%');
        }

        // Linux system directories only
        if ($request->filled('linux_only') && filter_var($request->linux_only, FILTER_VALIDATE_BOOLEAN)) {
            $query->linuxRoot();
        }

        $perPage = min($request->get('per_page', $this->perPage), 100);
        
        $directories = $query->orderBy('directory_path')
                           ->orderBy('created_at', 'desc')
                           ->paginate($perPage);

        return MonitoredDirectoryResource::collection($directories)
            ->additional([
                'meta' => [
                    'total_active' => MonitoredDirectory::active()->count(),
                    'total_inactive' => MonitoredDirectory::where('is_active', false)->count(),
                    'linux_system_dirs' => MonitoredDirectory::linuxRoot()->count()
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
     *      path="/api/monitored-directories",
     *      operationId="storeMonitoredDirectory",
     *      tags={"Monitored Directories"},
     *      summary="Add new directory to monitoring",
     *      description="Creates a new monitored directory entry",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"host_id","directory_path"},
     *              @OA\Property(property="host_id", type="integer", example=1),
     *              @OA\Property(property="directory_path", type="string", example="/var/log"),
     *              @OA\Property(property="is_active", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Directory added to monitoring",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/MonitoredDirectory"),
     *              @OA\Property(property="message", type="string", example="Directory added to monitoring successfully")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=409, description="Directory already monitored"),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Store a newly created monitored directory
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'host_id' => 'required|integer|exists:hosts,host_id',
                'directory_path' => 'required|string|max:500',
                'is_active' => 'boolean'
            ]);

            // Check if directory is already monitored on this host
            $existing = MonitoredDirectory::where('host_id', $validated['host_id'])
                                        ->where('directory_path', $validated['directory_path'])
                                        ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Directory is already being monitored on this host',
                    'existing_directory' => new MonitoredDirectoryResource($existing)
                ], 409);
            }

            $directory = MonitoredDirectory::create([
                'host_id' => $validated['host_id'],
                'directory_path' => $validated['directory_path'],
                'is_active' => $validated['is_active'] ?? true
            ]);

            $directory->load(['host', 'latestMetric']);

            return response()->json([
                'data' => new MonitoredDirectoryResource($directory),
                'message' => 'Directory added to monitoring successfully'
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
     *      path="/api/monitored-directories/{id}",
     *      operationId="getMonitoredDirectory",
     *      tags={"Monitored Directories"},
     *      summary="Get monitored directory details",
     *      description="Returns specific monitored directory with recent metrics",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Directory ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Directory details",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/MonitoredDirectory")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Directory not found")
     * )
     */
    /// <summary>
    /// Display the specified monitored directory
    /// </summary>
    /// <param>MonitoredDirectory $monitoredDirectory</param>
    /// <returns>JsonResponse</returns>
    public function show(MonitoredDirectory $monitoredDirectory): JsonResponse
    {
        $monitoredDirectory->load([
            'host', 
            'latestMetric',
            'directoryMetrics' => function($query) {
                $query->latest('timestamp')->limit(10);
            }
        ]);

        return response()->json([
            'data' => new MonitoredDirectoryResource($monitoredDirectory)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MonitoredDirectory $monitoredDirectory)
    {
        //
    }

    /**
     * @OA\Put(
     *      path="/api/monitored-directories/{id}",
     *      operationId="updateMonitoredDirectory",
     *      tags={"Monitored Directories"},
     *      summary="Update monitored directory",
     *      description="Updates monitored directory settings",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Directory ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="directory_path", type="string", example="/var/log"),
     *              @OA\Property(property="is_active", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Directory updated successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/MonitoredDirectory"),
     *              @OA\Property(property="message", type="string", example="Directory updated successfully")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="Directory not found")
     * )
     */
    /// <summary>
    /// Update the specified monitored directory
    /// </summary>
    /// <param>Request $request</param>
    /// <param>MonitoredDirectory $monitoredDirectory</param>
    /// <returns>JsonResponse</returns>
    public function update(Request $request, MonitoredDirectory $monitoredDirectory): JsonResponse
    {
        try {
            $validated = $request->validate([
                'directory_path' => 'sometimes|required|string|max:500',
                'is_active' => 'sometimes|boolean'
            ]);

            // Check if changing path to an already monitored one
            if (isset($validated['directory_path']) && $validated['directory_path'] !== $monitoredDirectory->directory_path) {
                $existing = MonitoredDirectory::where('host_id', $monitoredDirectory->host_id)
                                            ->where('directory_path', $validated['directory_path'])
                                            ->where('directory_id', '!=', $monitoredDirectory->directory_id)
                                            ->first();

                if ($existing) {
                    return response()->json([
                        'message' => 'Directory path is already being monitored on this host'
                    ], 409);
                }
            }

            $monitoredDirectory->update($validated);
            $monitoredDirectory->load(['host', 'latestMetric']);

            return response()->json([
                'data' => new MonitoredDirectoryResource($monitoredDirectory),
                'message' => 'Directory updated successfully'
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
     *      path="/api/monitored-directories/{id}",
     *      operationId="deleteMonitoredDirectory",
     *      tags={"Monitored Directories"},
     *      summary="Remove directory from monitoring",
     *      description="Deletes monitored directory and all its metrics",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="id",
     *          description="Directory ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=204,
     *          description="Directory removed from monitoring"
     *      ),
     *      @OA\Response(response=404, description="Directory not found")
     * )
     */
    /// <summary>
    /// Remove the specified directory from monitoring
    /// </summary>
    /// <param>MonitoredDirectory $monitoredDirectory</param>
    /// <returns>JsonResponse</returns>
    public function destroy(MonitoredDirectory $monitoredDirectory): JsonResponse
    {
        $monitoredDirectory->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *      path="/api/monitored-directories/host/{host}",
     *      operationId="getMonitoredDirectoriesByHost",
     *      tags={"Monitored Directories"},
     *      summary="Get directories for specific host",
     *      description="Returns all monitored directories for a specific host",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host",
     *          description="Host ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="active_only",
     *          description="Show only active directories",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Directories for the host",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MonitoredDirectory")),
     *              @OA\Property(property="host_info", type="object",
     *                  @OA\Property(property="host_id", type="integer"),
     *                  @OA\Property(property="host_name", type="string"),
     *                  @OA\Property(property="ip_address", type="string")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Get all monitored directories for a specific host
    /// </summary>
    /// <param>Request $request</param>
    /// <param>Host $host</param>
    /// <returns>JsonResponse</returns>
    public function getByHost(Request $request, Host $host): JsonResponse
    {
        $query = $host->monitoredDirectories()->with(['latestMetric']);

        if ($request->filled('active_only') && filter_var($request->active_only, FILTER_VALIDATE_BOOLEAN)) {
            $query->active();
        }

        $directories = $query->orderBy('directory_path')->get();

        return response()->json([
            'data' => MonitoredDirectoryResource::collection($directories),
            'host_info' => [
                'host_id' => $host->host_id,
                'host_name' => $host->host_name,
                'ip_address' => $host->ip_address,
                'operating_system' => $host->operating_system
            ],
            'meta' => [
                'total_directories' => $directories->count(),
                'active_directories' => $directories->where('is_active', true)->count(),
                'inactive_directories' => $directories->where('is_active', false)->count()
            ]
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/monitored-directories/bulk",
     *      operationId="bulkCreateMonitoredDirectories",
     *      tags={"Monitored Directories"},
     *      summary="Bulk add directories to monitoring",
     *      description="Add multiple directories to monitoring at once",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"host_id","directories"},
     *              @OA\Property(property="host_id", type="integer", example=1),
     *              @OA\Property(
     *                  property="directories",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="directory_path", type="string", example="/var/log"),
     *                      @OA\Property(property="is_active", type="boolean", example=true)
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Directories added to monitoring",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="created", type="array", @OA\Items(ref="#/components/schemas/MonitoredDirectory")),
     *              @OA\Property(property="skipped", type="array", @OA\Items(type="string")),
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="Host not found")
     * )
     */
    /// <summary>
    /// Bulk create monitored directories
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'host_id' => 'required|integer|exists:hosts,host_id',
                'directories' => 'required|array|min:1|max:50',
                'directories.*.directory_path' => 'required|string|max:500',
                'directories.*.is_active' => 'boolean'
            ]);

            $created = [];
            $skipped = [];

            foreach ($validated['directories'] as $dirData) {
                $existing = MonitoredDirectory::where('host_id', $validated['host_id'])
                                            ->where('directory_path', $dirData['directory_path'])
                                            ->first();

                if ($existing) {
                    $skipped[] = $dirData['directory_path'] . ' (already monitored)';
                    continue;
                }

                $directory = MonitoredDirectory::create([
                    'host_id' => $validated['host_id'],
                    'directory_path' => $dirData['directory_path'],
                    'is_active' => $dirData['is_active'] ?? true
                ]);

                $directory->load(['host', 'latestMetric']);
                $created[] = $directory;
            }

            return response()->json([
                'created' => MonitoredDirectoryResource::collection(collect($created)),
                'skipped' => $skipped,
                'message' => 'Bulk operation completed. Created: ' . count($created) . ', Skipped: ' . count($skipped)
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    #endregion

    #region Private Methods

    /// <summary>
    /// Get common Linux system directories for quick setup
    /// </summary>
    /// <returns>array</returns>
    private function getCommonLinuxDirectories(): array
    {
        return [
            '/root' => 'Root user home directory',
            '/var' => 'Variable data files',
            '/tmp' => 'Temporary files',
            '/var/log' => 'Log files',
            '/home' => 'User home directories',
            '/opt' => 'Optional application software',
            '/usr' => 'User utilities and applications',
            '/etc' => 'Configuration files'
        ];
    }

    #endregion
}