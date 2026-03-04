<?php

// app/Http/Controllers/DatasetController.php

namespace App\Http\Controllers;

use App\Http\Requests\Dataset\CreateDatasetRequest;
use App\Http\Requests\Dataset\UpdateDatasetRequest;
use App\Http\Resources\DatasetResource;
use App\Services\DatasetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DatasetController extends Controller
{
  public function __construct(
    protected DatasetService $service
  ) {}

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/datasets
    |--------------------------------------------------------------------------
    */

  public function index(Request $request): AnonymousResourceCollection
  {
    $perPage  = min((int) $request->get('per_page', 20), 100);
    $datasets = $this->service->list($perPage);

    return DatasetResource::collection($datasets);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/datasets
    |--------------------------------------------------------------------------
    */

  public function store(CreateDatasetRequest $request): JsonResponse
  {
    $dataset = $this->service->create($request->validated());

    return (new DatasetResource($dataset))
      ->response()
      ->setStatusCode(201);
  }

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/datasets/{dataset}
    |--------------------------------------------------------------------------
    */

  public function show(string $id): DatasetResource
  {
    $dataset = $this->service->findOrFail($id);

    return new DatasetResource($dataset);
  }

  /*
    |--------------------------------------------------------------------------
    | PUT /api/v1/datasets/{dataset}
    |--------------------------------------------------------------------------
    */

  public function update(UpdateDatasetRequest $request, string $id): DatasetResource
  {
    $dataset = $this->service->update($id, $request->validated());

    return new DatasetResource($dataset);
  }

  /*
    |--------------------------------------------------------------------------
    | DELETE /api/v1/datasets/{dataset}
    |--------------------------------------------------------------------------
    */

  public function destroy(string $id): JsonResponse
  {
    $this->service->delete($id);

    return response()->json([
      'message' => 'Dataset deleted successfully.',
    ], 200);
  }
}
