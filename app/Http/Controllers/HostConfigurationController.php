<?php

namespace App\Http\Controllers;

use App\Http\Resources\HostConfigurationResource;
use App\Models\HostConfiguration;
use App\Models\Host;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Host Configurations",
 *     description="API Endpoints for managing host monitoring configurations (UC24)"
 * )
 */
class HostConfigurationController extends Controller
{
    #region Properties
    
    /// <summary>
    /// Validation rules for host configuration data
    /// </summary>
    private array $validationRules = [
        'host_id' => 'required|integer|exists:hosts,host_id',
        'data_collection_interval' => 'sometimes|integer|min:30|max:3600',
        'enable_cpu_monitoring' => 'sometimes|boolean',
        'enable_ram_monitoring' => 'sometimes|boolean',
        'enable_disk_monitoring' => 'sometimes|boolean',
        'enable_network_monitoring' => 'sometimes|boolean',
        'updated_by_user_id' => 'sometimes|integer|exists:users,id'
    ];

    #endregion

    #region Methods

    /**
     * @OA\Get(
     *      path="/api/host-configurations",
     *      operationId="getHostConfigurationsList",
     *      tags={"Host Configurations"},
     *      summary="Get list of host configurations (UC24)",
     *      description="Returns list of host configurations with filtering options",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="host_id",
     *          description="Filter by host ID",
     *          required=false,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/HostConfiguration"))
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthorized")
     * )
     */
    /// <summary>
    /// Display a listing of host configurations
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function index(Request $request): JsonResponse
    {
        $query = HostConfiguration::with(['host', 'updatedByUser']);

        // Filtrowanie według host_id
        if ($request->has('host_id')) {
            $query->where('host_id', $request->host_id);
        }

        $configurations = $query->orderBy('updated_at', 'desc')->get();

        return response()->json([
            'data' => HostConfigurationResource::collection($configurations),
            'summary' => [
                'total_configurations' => $configurations->count(),
                'hosts_with_custom_config' => $configurations->unique('host_id')->count(),
                'avg_collection_interval' => $configurations->avg('data_collection_interval'),
                'most_common_interval' => $configurations->mode('data_collection_interval')[0] ?? 120
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
     *      path="/api/host-configurations",
     *      operationId="createHostConfiguration",
     *      tags={"Host Configurations"},
     *      summary="Create new host configuration (UC24)",
     *      description="Create new monitoring configuration for host",
     *      security={{"sanctum":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"host_id"},
     *              @OA\Property(property="host_id", type="integer", description="Host ID"),
     *              @OA\Property(property="data_collection_interval", type="integer", description="Interval in seconds (30-3600)", default=120),
     *              @OA\Property(property="enable_cpu_monitoring", type="boolean", default=true),
     *              @OA\Property(property="enable_ram_monitoring", type="boolean", default=true),
     *              @OA\Property(property="enable_disk_monitoring", type="boolean", default=true),
     *              @OA\Property(property="enable_network_monitoring", type="boolean", default=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Configuration created successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/HostConfiguration")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=409, description="Configuration already exists")
     * )
     */
    /// <summary>
    /// Store a newly created host configuration in storage
    /// </summary>
    /// <param>Request $request</param>
    /// <returns>JsonResponse</returns>
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->validationRules);
        
        // Sprawdzenie czy konfiguracja dla tego hosta już istnieje
        $existingConfig = HostConfiguration::where('host_id', $validated['host_id'])->first();
        if ($existingConfig) {
            return response()->json([
                'message' => 'Configuration for this host already exists. Use PUT to update.',
                'existing_configuration_id' => $existingConfig->configuration_id
            ], 409);
        }

        // Sprawdzenie czy host istnieje
        $host = Host::where('host_id', $validated['host_id'])->firstOrFail();
        
        // Ustawienie domyślnych wartości
        $validated['updated_by_user_id'] = Auth::id();
        $validated['data_collection_interval'] = $validated['data_collection_interval'] ?? 120;
        $validated['enable_cpu_monitoring'] = $validated['enable_cpu_monitoring'] ?? true;
        $validated['enable_ram_monitoring'] = $validated['enable_ram_monitoring'] ?? true;
        $validated['enable_disk_monitoring'] = $validated['enable_disk_monitoring'] ?? true;
        $validated['enable_network_monitoring'] = $validated['enable_network_monitoring'] ?? true;

        $configuration = HostConfiguration::create($validated);
        $configuration->load(['host', 'updatedByUser']);

        return response()->json([
            'message' => "Host configuration for '{$host->host_name}' created successfully",
            'data' => new HostConfigurationResource($configuration)
        ], 201);
    }

    /**
     * @OA\Get(
     *      path="/api/host-configurations/{hostConfiguration}",
     *      operationId="showHostConfiguration",
     *      tags={"Host Configurations"},
     *      summary="Get specific host configuration (UC24)",
     *      description="Get configuration details for specific configuration",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="hostConfiguration",
     *          description="Configuration ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", ref="#/components/schemas/HostConfiguration")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Configuration not found")
     * )
     */
    /// <summary>
    /// Display the specified host configuration
    /// </summary>
    /// <param>HostConfiguration $hostConfiguration</param>
    /// <returns>HostConfigurationResource</returns>
    public function show(HostConfiguration $hostConfiguration): HostConfigurationResource
    {
        $hostConfiguration->load(['host', 'updatedByUser']);
        return new HostConfigurationResource($hostConfiguration);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HostConfiguration $hostConfiguration)
    {
        //
    }

    /**
     * @OA\Put(
     *      path="/api/host-configurations/{hostConfiguration}",
     *      operationId="updateHostConfiguration",
     *      tags={"Host Configurations"},
     *      summary="Update host configuration (UC24)",
     *      description="Update monitoring configuration for host",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="hostConfiguration",
     *          description="Configuration ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="data_collection_interval", type="integer", description="Interval in seconds (30-3600)"),
     *              @OA\Property(property="enable_cpu_monitoring", type="boolean"),
     *              @OA\Property(property="enable_ram_monitoring", type="boolean"),
     *              @OA\Property(property="enable_disk_monitoring", type="boolean"),
     *              @OA\Property(property="enable_network_monitoring", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Configuration updated successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string"),
     *              @OA\Property(property="data", ref="#/components/schemas/HostConfiguration")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=404, description="Configuration not found")
     * )
     */
    /// <summary>
    /// Update the specified host configuration in storage
    /// </summary>
    /// <param>Request $request</param>
    /// <param>HostConfiguration $hostConfiguration</param>
    /// <returns>JsonResponse</returns>
    public function update(Request $request, HostConfiguration $hostConfiguration): JsonResponse
    {
        // Usunięcie host_id z reguł walidacji dla update
        $updateRules = $this->validationRules;
        unset($updateRules['host_id']);
        
        $validated = $request->validate($updateRules);
        
        // Automatyczne ustawienie użytkownika aktualizującego
        $validated['updated_by_user_id'] = Auth::id();

        $hostConfiguration->update($validated);
        $hostConfiguration->load(['host', 'updatedByUser']);

        return response()->json([
            'message' => "Host configuration for '{$hostConfiguration->host->host_name}' updated successfully",
            'data' => new HostConfigurationResource($hostConfiguration)
        ]);
    }

    /**
     * @OA\Delete(
     *      path="/api/host-configurations/{hostConfiguration}",
     *      operationId="deleteHostConfiguration",
     *      tags={"Host Configurations"},
     *      summary="Delete host configuration (UC24)",
     *      description="Delete monitoring configuration (resets to defaults)",
     *      security={{"sanctum":{}}},
     *      @OA\Parameter(
     *          name="hostConfiguration",
     *          description="Configuration ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Configuration deleted successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Configuration not found")
     * )
     */
    /// <summary>
    /// Remove the specified host configuration from storage
    /// </summary>
    /// <param>HostConfiguration $hostConfiguration</param>
    /// <returns>JsonResponse</returns>
    public function destroy(HostConfiguration $hostConfiguration): JsonResponse
    {
        $hostName = $hostConfiguration->host->host_name;
        $hostConfiguration->delete();

        return response()->json([
            'message' => "Host configuration for '{$hostName}' deleted successfully. Default settings will be used."
        ]);
    }

    #endregion
}