<?php

// app/Http/Controllers/MissionController.php

namespace App\Http\Controllers;

use App\Http\Requests\Mission\CreateMissionRequest;
use App\Http\Resources\MissionResource;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MissionController extends Controller
{
  public function __construct(
    protected MissionService $service
  ) {}

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/missions
    |--------------------------------------------------------------------------
    */

  public function index(Request $request): AnonymousResourceCollection
  {
    $perPage  = min((int) $request->get('per_page', 20), 100);
    $missions = $this->service->list($perPage);

    return MissionResource::collection($missions);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/missions
    |--------------------------------------------------------------------------
    */

  public function store(CreateMissionRequest $request): JsonResponse
  {
    $mission = $this->service->create($request->validated());

    return (new MissionResource($mission))
      ->response()
      ->setStatusCode(201);
  }

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/missions/{mission}
    |--------------------------------------------------------------------------
    */

  public function show(string $id): MissionResource
  {
    $mission = $this->service->findOrFail($id);

    return new MissionResource($mission);
  }

  /*
    |--------------------------------------------------------------------------
    | PUT /api/v1/missions/{mission}
    |--------------------------------------------------------------------------
    */

  public function update(Request $request, string $id): MissionResource
  {
    $data = $request->validate([
      'name'     => ['sometimes', 'string', 'max:255'],
      'operator' => ['sometimes', 'string', 'max:255'],
      'metadata' => ['nullable', 'array'],
    ]);

    $mission = $this->service->update($id, $data);

    return new MissionResource($mission);
  }

  /*
    |--------------------------------------------------------------------------
    | PATCH /api/v1/missions/{mission}/status
    |--------------------------------------------------------------------------
    */

  public function status(Request $request, string $id): MissionResource
  {
    $data = $request->validate([
      'status' => ['required', 'string', 'in:active,completed,aborted'],
    ]);

    $mission = $this->service->transition($id, $data['status']);

    return new MissionResource($mission);
  }
}
