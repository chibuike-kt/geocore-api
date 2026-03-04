<?php

// app/Http/Controllers/QueryController.php

namespace App\Http\Controllers;

use App\Http\Requests\Query\IntersectsQueryRequest;
use App\Http\Requests\Query\NearestQueryRequest;
use App\Http\Requests\Query\RadiusQueryRequest;
use App\Http\Requests\Query\WithinQueryRequest;
use App\Http\Resources\SpatialQueryResource;
use App\Services\SpatialQueryService;
use Illuminate\Http\JsonResponse;

class QueryController extends Controller
{
  public function __construct(
    protected SpatialQueryService $service
  ) {}

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/query/within
    |
    | Find all features completely inside a given polygon.
    | Uses ST_Within.
    |--------------------------------------------------------------------------
    */

  public function within(WithinQueryRequest $request): JsonResponse
  {
    $results = $this->service->within(
      datasetId: $request->input('dataset_id'),
      polygonGeometry: $request->input('geometry'),
    );

    return $this->queryResponse($results, 'within');
  }

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/query/radius
    |
    | Find all features within N metres of a coordinate.
    | Uses ST_DWithin on geography type (accurate metre distances).
    |--------------------------------------------------------------------------
    */

  public function radius(RadiusQueryRequest $request): JsonResponse
  {
    $results = $this->service->radius(
      datasetId: $request->input('dataset_id'),
      lat: (float) $request->input('lat'),
      lng: (float) $request->input('lng'),
      radiusMeters: (float) $request->input('radius'),
    );

    return $this->queryResponse($results, 'radius', [
      'center' => [
        'lat' => (float) $request->input('lat'),
        'lng' => (float) $request->input('lng'),
      ],
      'radius_meters' => (float) $request->input('radius'),
    ]);
  }

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/query/nearest
    |
    | Find the N nearest features to a coordinate.
    | Uses KNN index operator <-> for performance.
    |--------------------------------------------------------------------------
    */

  public function nearest(NearestQueryRequest $request): JsonResponse
  {
    $results = $this->service->nearest(
      datasetId: $request->input('dataset_id'),
      lat: (float) $request->input('lat'),
      lng: (float) $request->input('lng'),
      limit: (int) $request->input('limit', 10),
    );

    return $this->queryResponse($results, 'nearest', [
      'center' => [
        'lat' => (float) $request->input('lat'),
        'lng' => (float) $request->input('lng'),
      ],
      'limit' => (int) $request->input('limit', 10),
    ]);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/query/intersects
    |
    | Find all features that intersect with a given geometry.
    | Uses ST_Intersects.
    |--------------------------------------------------------------------------
    */

  public function intersects(IntersectsQueryRequest $request): JsonResponse
  {
    $results = $this->service->intersects(
      datasetId: $request->input('dataset_id'),
      geometry: $request->input('geometry'),
    );

    return $this->queryResponse($results, 'intersects');
  }

  /*
    |--------------------------------------------------------------------------
    | Response Builder
    |--------------------------------------------------------------------------
    */

  private function queryResponse(
    \Illuminate\Support\Collection $results,
    string $queryType,
    array $meta = []
  ): JsonResponse {
    return response()->json([
      'query'   => $queryType,
      'count'   => $results->count(),
      'meta'    => $meta,
      'data'    => SpatialQueryResource::collection($results),
    ]);
  }
}
