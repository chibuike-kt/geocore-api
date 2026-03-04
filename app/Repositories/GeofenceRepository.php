<?php

// app/Repositories/GeofenceRepository.php

namespace App\Repositories;

use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Repositories\Contracts\GeofenceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeofenceRepository implements GeofenceRepositoryInterface
{
  public function __construct(
    protected Geofence      $model,
    protected GeofenceEvent $eventModel,
  ) {}

  /*
    |--------------------------------------------------------------------------
    | Read Operations
    |--------------------------------------------------------------------------
    */

  public function findAll(int $perPage = 20): LengthAwarePaginator
  {
    return $this->model
      ->orderBy('created_at', 'desc')
      ->paginate($perPage);
  }

  public function findById(string $id): ?Geofence
  {
    return $this->model->find($id);
  }

  public function findActive(): Collection
  {
    return $this->model
      ->where('active', true)
      ->orderBy('name')
      ->get();
  }

  /**
   * Find all active geofences that contain a given point.
   * Uses ST_Contains — point must be inside the polygon.
   */
  public function findContainingPoint(float $lat, float $lng): Collection
  {
    return collect(DB::select("
            SELECT
                id, name, type, altitude_min, altitude_max, active, metadata,
                created_at,
                ST_AsGeoJSON(geometry)::json AS geometry
            FROM geofences
            WHERE active = TRUE
              AND ST_Contains(
                    geometry,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)
                  )
        ", [$lng, $lat]));
  }

  /**
   * Find all active geofences intersecting a given point.
   * ST_Intersects is slightly more inclusive than ST_Contains
   * (handles edge/boundary cases).
   */
  public function findIntersectingPoint(float $lat, float $lng): Collection
  {
    return collect(DB::select("
            SELECT
                id, name, type, altitude_min, altitude_max, active, metadata,
                created_at,
                ST_AsGeoJSON(geometry)::json AS geometry
            FROM geofences
            WHERE active = TRUE
              AND ST_Intersects(
                    geometry,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)
                  )
        ", [$lng, $lat]));
  }

  /*
    |--------------------------------------------------------------------------
    | Write Operations
    |--------------------------------------------------------------------------
    */

  public function create(array $data): Geofence
  {
    return DB::transaction(function () use ($data) {
      $id = (string) \Illuminate\Support\Str::uuid();

      DB::statement("
                INSERT INTO geofences (
                    id, name, type, geometry,
                    altitude_min, altitude_max,
                    active, metadata,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?,
                    ST_GeomFromText(?, 4326),
                    ?, ?,
                    ?, ?::jsonb,
                    NOW(), NOW()
                )
            ", [
        $id,
        $data['name'],
        $data['type'] ?? Geofence::TYPE_RESTRICTION,
        $data['geometry_wkt'],
        $data['altitude_min'] ?? null,
        $data['altitude_max'] ?? null,
        $data['active']       ?? true,
        json_encode($data['metadata'] ?? []),
      ]);

      return $this->model->findOrFail($id);
    });
  }

  public function update(string $id, array $data): Geofence
  {
    return DB::transaction(function () use ($id, $data) {
      $geofence = $this->model->findOrFail($id);
      $geofence->update($data);
      return $geofence->fresh();
    });
  }

  /*
    |--------------------------------------------------------------------------
    | Event Recording
    |--------------------------------------------------------------------------
    */

  public function recordEvent(array $data): GeofenceEvent
  {
    DB::statement("
            INSERT INTO geofence_events (
                geofence_id, mission_id, event_type,
                position, recorded_at, created_at
            ) VALUES (
                ?, ?, ?,
                ST_SetSRID(ST_MakePoint(?, ?), 4326),
                ?, NOW()
            )
        ", [
      $data['geofence_id'],
      $data['mission_id'] ?? null,
      $data['event_type'],
      $data['lng'],
      $data['lat'],
      $data['recorded_at'],
    ]);

    return $this->eventModel
      ->where('geofence_id', $data['geofence_id'])
      ->orderBy('id', 'desc')
      ->first();
  }

  /**
   * Get the last known event type for a mission/geofence pair.
   * Used by the evaluator to determine enter vs exit transitions.
   */
  public function getLastEvent(string $geofenceId, ?string $missionId): ?GeofenceEvent
  {
    return $this->eventModel
      ->where('geofence_id', $geofenceId)
      ->when($missionId, fn($q) => $q->where('mission_id', $missionId))
      ->orderBy('recorded_at', 'desc')
      ->first();
  }

  /**
   * Get all events for a geofence, ordered by time.
   */
  public function getEvents(string $geofenceId): Collection
  {
    return $this->eventModel
      ->where('geofence_id', $geofenceId)
      ->orderBy('recorded_at', 'desc')
      ->get();
  }
}
