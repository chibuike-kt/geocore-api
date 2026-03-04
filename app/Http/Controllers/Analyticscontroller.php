<?php

// app/Http/Controllers/AnalyticsController.php

namespace App\Http\Controllers;

use App\Http\Requests\Analytics\BufferRequest;
use App\Http\Requests\Analytics\ClusterRequest;
use App\Http\Requests\Analytics\CoverageRequest;
use App\Http\Requests\Analytics\DensityRequest;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
  public function __construct(
    protected AnalyticsService $service
  ) {}

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/analytics/buffer
    |
    | Expands a geometry by a radius in metres.
    | Optionally returns features from a dataset that fall inside the buffer.
    |--------------------------------------------------------------------------
    */

  public function buffer(BufferRequest $request): JsonResponse
  {
    $result = $this->service->buffer($request->validated());

    return response()->json($result);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/analytics/coverage
    |
    | Calculates total polygon area coverage in a dataset.
    | Optionally calculates coverage % within a boundary polygon.
    |--------------------------------------------------------------------------
    */

  public function coverage(CoverageRequest $request): JsonResponse
  {
    $result = $this->service->coverage(
      datasetId: $request->input('dataset_id'),
      boundary: $request->input('boundary'),
    );

    return response()->json($result);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/analytics/density
    |
    | Overlays a grid on a dataset and counts features per cell.
    | cell_size is in metres.
    |--------------------------------------------------------------------------
    */

  public function density(DensityRequest $request): JsonResponse
  {
    $result = $this->service->density(
      datasetId: $request->input('dataset_id'),
      cellSizeMeters: (float) $request->input('cell_size'),
    );

    return response()->json($result);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/analytics/cluster
    |
    | Groups point features into spatial clusters using DBSCAN.
    |--------------------------------------------------------------------------
    */

  public function cluster(ClusterRequest $request): JsonResponse
  {
    $result = $this->service->cluster(
      datasetId: $request->input('dataset_id'),
      radiusMeters: (float) $request->input('radius'),
      minPoints: (int) $request->input('min_points', 2),
    );

    return response()->json($result);
  }

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/analytics/extent
    |
    | Returns the bounding box of all features in a dataset.
    |--------------------------------------------------------------------------
    */

  public function extent(Request $request): JsonResponse
  {
    $request->validate([
      'dataset_id' => ['required', 'uuid'],
    ]);

    $result = $this->service->extent($request->input('dataset_id'));

    return response()->json($result);
  }
}
