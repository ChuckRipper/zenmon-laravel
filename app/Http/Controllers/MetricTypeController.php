<?php

namespace App\Http\Controllers;

use App\Http\Resources\MetricTypeResource;
use App\Models\MetricType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MetricTypeController extends Controller
{
    #region Properties
    
    /// <summary>
    /// Validation rules for metric type data
    /// </summary>
    private array $validationRules = [
        'metric_name' => 'required|string|max:50|unique:metric_types,metric_name',
        'unit' => 'required|string|max:10',
        'description' => 'nullable|string|max:200'
    ];

    /// <summary>
    /// Validation rules for updating metric type
    /// </summary>
    private array $updateValidationRules = [
        'metric_name' => 'required|string|max:50',
        'unit' => 'required|string|max:10',
        'description' => 'nullable|string|max:200'
    ];

    #endregion

    #region Methods

    /// <summary>
    /// Display a listing of metric types
    /// </summary>
    /// <param name="request">HTTP request with optional filters</param>
    /// <returns>JsonResponse with paginated metric types</returns>
    public function index(Request $request): JsonResponse
    {
        $query = MetricType::query();

        // Search by metric name
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('metric_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        // Filter by unit
        if ($request->has('unit')) {
            $query->where('unit', $request->get('unit'));
        }

        // Order by metric name
        $query->orderBy('metric_name');

        $metricTypes = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => MetricTypeResource::collection($metricTypes),
            'meta' => [
                'current_page' => $metricTypes->currentPage(),
                'per_page' => $metricTypes->perPage(),
                'total' => $metricTypes->total(),
                'last_page' => $metricTypes->lastPage()
            ]
        ]);
    }

    /// <summary>
    /// Store a newly created metric type
    /// </summary>
    /// <param name="request">HTTP request with metric type data</param>
    /// <returns>JsonResponse with created metric type or error</returns>
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->validationRules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $metricType = MetricType::create([
                'metric_name' => $request->metric_name,
                'unit' => $request->unit,
                'description' => $request->description
            ]);

            return response()->json([
                'message' => 'Metric type created successfully',
                'data' => new MetricTypeResource($metricType)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating metric type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /// <summary>
    /// Display the specified metric type
    /// </summary>
    /// <param name="id">Metric type ID</param>
    /// <returns>JsonResponse with metric type data or 404</returns>
    public function show(int $id): JsonResponse
    {
        $metricType = MetricType::find($id);

        if (!$metricType) {
            return response()->json([
                'message' => 'Metric type not found'
            ], 404);
        }

        return response()->json([
            'data' => new MetricTypeResource($metricType)
        ]);
    }

    /// <summary>
    /// Update the specified metric type
    /// </summary>
    /// <param name="request">HTTP request with updated data</param>
    /// <param name="id">Metric type ID</param>
    /// <returns>JsonResponse with updated metric type or error</returns>
    public function update(Request $request, int $id): JsonResponse
    {
        $metricType = MetricType::find($id);

        if (!$metricType) {
            return response()->json([
                'message' => 'Metric type not found'
            ], 404);
        }

        // Modify validation rules for unique check (exclude current record)
        $rules = $this->updateValidationRules;
        $rules['metric_name'] .= "|unique:metric_types,metric_name,{$id},metric_type_id";

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $metricType->update([
                'metric_name' => $request->metric_name,
                'unit' => $request->unit,
                'description' => $request->description
            ]);

            return response()->json([
                'message' => 'Metric type updated successfully',
                'data' => new MetricTypeResource($metricType)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating metric type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /// <summary>
    /// Remove the specified metric type
    /// Only allow deletion if no metrics are using this type
    /// </summary>
    /// <param name="id">Metric type ID</param>
    /// <returns>JsonResponse with success message or error</returns>
    public function destroy(int $id): JsonResponse
    {
        $metricType = MetricType::find($id);

        if (!$metricType) {
            return response()->json([
                'message' => 'Metric type not found'
            ], 404);
        }

        // Check if metric type is being used by any metrics
        if ($metricType->metrics()->exists()) {
            return response()->json([
                'message' => 'Cannot delete metric type that is being used by existing metrics',
                'metrics_count' => $metricType->metrics()->count()
            ], 409); // Conflict
        }

        // Check if metric type is being used by any alert thresholds
        if ($metricType->alertThresholds()->exists()) {
            return response()->json([
                'message' => 'Cannot delete metric type that has alert thresholds configured',
                'thresholds_count' => $metricType->alertThresholds()->count()
            ], 409); // Conflict
        }

        try {
            $metricType->delete();

            return response()->json([
                'message' => 'Metric type deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting metric type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /// <summary>
    /// Get metric types with recent metrics count
    /// Useful for dashboard statistics
    /// </summary>
    /// <returns>JsonResponse with metric types and statistics</returns>
    public function getWithStats(): JsonResponse
    {
        $metricTypes = MetricType::withCount([
            'metrics',
            'metrics as recent_metrics_count' => function ($query) {
                $query->where('timestamp', '>=', now()->subHours(24));
            },
            'alertThresholds'
        ])->orderBy('metric_name')->get();

        return response()->json([
            'data' => $metricTypes->map(function ($metricType) {
                return [
                    'metric_type_id' => $metricType->metric_type_id,
                    'metric_name' => $metricType->metric_name,
                    'unit' => $metricType->unit,
                    'description' => $metricType->description,
                    'total_metrics' => $metricType->metrics_count,
                    'recent_metrics_24h' => $metricType->recent_metrics_count,
                    'alert_thresholds_count' => $metricType->alert_thresholds_count,
                    'created_at' => $metricType->created_at,
                    'updated_at' => $metricType->updated_at
                ];
            })
        ]);
    }

    /// <summary>
    /// Get available units for creating new metric types
    /// </summary>
    /// <returns>JsonResponse with common units and currently used units</returns>
    public function getAvailableUnits(): JsonResponse
    {
        $commonUnits = [
            '%' => 'Percentage',
            'ms' => 'Milliseconds',
            's' => 'Seconds',
            'MB' => 'Megabytes',
            'GB' => 'Gigabytes',
            'KB/s' => 'Kilobytes per second',
            'MB/s' => 'Megabytes per second',
            'count' => 'Count/Number',
            '°C' => 'Celsius',
            'RPM' => 'Revolutions per minute'
        ];

        return response()->json([
            'common_units' => $commonUnits,
            'used_units' => MetricType::distinct()->pluck('unit')->sort()->values()
        ]);
    }

    #endregion
}