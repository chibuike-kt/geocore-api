<?php

// app/Http/Controllers/GeofenceController.php

namespace App\Http\Controllers;

use App\Http\Requests\Geofence\CreateGeofenceRequest;
use App\Http\Requests\Geofence\EvaluateGeofenceRequest;
use App\Http\Resources\GeofenceResource;
use App\Services\GeofenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GeofenceController extends Controller
{
  public function __construct(
    protected GeofenceService $service
  ) {}

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/geofences
    |--------------------------------------------------------------------------
    */

  public function index(Request $request): AnonymousResourceCollection
  {
    $perPage   = min((int) $request->get('per_page', 20), 100);
    $geofences = $this->service->list($perPage);

    return GeofenceResource::collection($geofences);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/geofences
    |--------------------------------------------------------------------------
    */

  public function store(CreateGeofenceRequest $request): JsonResponse
  {
    $geofence = $this->service->create($request->validated());

    return (new GeofenceResource($geofence))
      ->response()
      ->setStatusCode(201);
  }

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/geofences/{geofence}
    |--------------------------------------------------------------------------
    */

  public function show(string $id): GeofenceResource
  {
    $geofence = $this->service->findOrFail($id);

    return new GeofenceResource($geofence);
  }

  /*
    |--------------------------------------------------------------------------
    | PUT /api/v1/geofences/{geofence}
    |--------------------------------------------------------------------------
    */

  public function update(Request $request, string $id): GeofenceResource
  {
    $data = $request->validate([
      'name'         => ['sometimes', 'string', 'max:255'],
      'active'       => ['sometimes', 'boolean'],
      'altitude_min' => ['nullable', 'numeric'],
      'altitude_max' => ['nullable', 'numeric'],
      'metadata'     => ['nullable', 'array'],
    ]);

    $geofence = $this->service->update($id, $data);

    return new GeofenceResource($geofence);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/geofences/evaluate
    |
    | Core evaluation endpoint. Takes a position and returns all
    | enter/exit events that occurred at that point.
    |--------------------------------------------------------------------------
    */

  public function evaluate(EvaluateGeofenceRequest $request): JsonResponse
  {
    $result = $this->service->evaluate($request->validated());

    return response()->json($result);
  }

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/geofences/{geofence}/events
    |--------------------------------------------------------------------------
    */

  public function events(string $id): JsonResponse
  {
    $events = $this->service->getEvents($id);

    return response()->json([
      'geofence_id' => $id,
      'count'       => $events->count(),
      'data'        => $events,
    ]);
  }
}
