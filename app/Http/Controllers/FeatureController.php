<?php

// app/Http/Controllers/FeatureController.php

namespace App\Http\Controllers;

use App\Http\Requests\Feature\BulkFeatureRequest;
use App\Http\Requests\Feature\CreateFeatureRequest;
use App\Http\Resources\FeatureResource;
use App\Services\FeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeatureController extends Controller
{
  public function __construct(
    protected FeatureService $service
  ) {}

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/datasets/{dataset}/features
    |--------------------------------------------------------------------------
    */

  public function index(Request $request, string $datasetId): AnonymousResourceCollection
  {
    $perPage  = min((int) $request->get('per_page', 100), 500);
    $features = $this->service->list($datasetId, $perPage);

    return FeatureResource::collection($features);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/datasets/{dataset}/features
    |--------------------------------------------------------------------------
    */

  public function store(CreateFeatureRequest $request, string $datasetId): JsonResponse
  {
    $feature = $this->service->create($datasetId, $request->validated());

    return (new FeatureResource($feature))
      ->response()
      ->setStatusCode(201);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/datasets/{dataset}/features/bulk
    |--------------------------------------------------------------------------
    */

  public function bulk(BulkFeatureRequest $request, string $datasetId): JsonResponse
  {
    $result = $this->service->bulkCreate($datasetId, $request->validated());

    return response()->json([
      'message'   => 'Bulk ingestion complete.',
      'submitted' => $result['submitted'],
      'inserted'  => $result['inserted'],
      'skipped'   => $result['skipped'],
      'errors'    => $result['errors'],
    ], 201);
  }
}
