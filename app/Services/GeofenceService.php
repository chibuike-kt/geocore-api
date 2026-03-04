<?php

// app/Services/GeofenceService.php

namespace App\Services;

use App\Exceptions\InvalidGeometryException;
use App\Geo\GeometryHelper;
use App\Models\AuditLog;
use App\Models\Geofence;
use App\Repositories\Contracts\GeofenceRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeofenceService
{
  public function __construct(
    protected GeofenceRepositoryInterface $repository,
    protected GeofenceEvaluator           $evaluator,
  ) {}

  /*
    |--------------------------------------------------------------------------
    | List
    |--------------------------------------------------------------------------
    */

  public function list(int $perPage = 20): LengthAwarePaginator
  {
    return $this->repository->findAll($perPage);
  }

  /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

  public function findOrFail(string $id): Geofence
  {
    $geofence = $this->repository->findById($id);

    if (!$geofence) {
      throw new ModelNotFoundException("Geofence [{$id}] not found.");
    }

    return $geofence;
  }

  /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

  public function create(array $data): Geofence
  {
    $geometry = $data['geometry'];

    GeometryHelper::validate($geometry);

    if ($geometry['type'] !== 'Polygon') {
      throw new InvalidGeometryException(
        'Geofences must use Polygon geometry.'
      );
    }

    $data['geometry_wkt'] = GeometryHelper::toWkt($geometry);

    $geofence = $this->repository->create($data);

    AuditLog::record(
      entityType: 'geofence',
      entityId: $geofence->id,
      action: 'created',
      payload: [
        'name' => $geofence->name,
        'type' => $geofence->type,
      ],
    );

    return $geofence;
  }

  /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

  public function update(string $id, array $data): Geofence
  {
    $this->findOrFail($id);

    $geofence = $this->repository->update($id, $data);

    AuditLog::record(
      entityType: 'geofence',
      entityId: $geofence->id,
      action: 'updated',
      payload: $data,
    );

    return $geofence;
  }

  /*
    |--------------------------------------------------------------------------
    | Evaluate a position against all active geofences
    |--------------------------------------------------------------------------
    */

  public function evaluate(array $data): array
  {
    return $this->evaluator->evaluate(
      lat: (float) $data['lat'],
      lng: (float) $data['lng'],
      altitude: isset($data['altitude']) ? (float) $data['altitude'] : null,
      missionId: $data['mission_id']  ?? null,
      recordedAt: $data['recorded_at'] ?? null,
    );
  }

  /*
    |--------------------------------------------------------------------------
    | Get events for a geofence
    |--------------------------------------------------------------------------
    */

  public function getEvents(string $id): Collection
  {
    $this->findOrFail($id);

    return $this->repository->getEvents($id);
  }

  /*
    |--------------------------------------------------------------------------
    | Resolve geometry for API responses
    |--------------------------------------------------------------------------
    */

  public function resolveGeometry(string $id): mixed
  {
    $result = DB::selectOne(
      'SELECT ST_AsGeoJSON(geometry)::json AS geometry FROM geofences WHERE id = ?',
      [$id]
    );

    return $result?->geometry ? json_decode($result->geometry, true) : null;
  }
}
