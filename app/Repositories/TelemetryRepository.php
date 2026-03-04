<?php

// app/Repositories/TelemetryRepository.php

namespace App\Repositories;

use App\Models\Telemetry;
use App\Repositories\Contracts\TelemetryRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TelemetryRepository implements TelemetryRepositoryInterface
{
  public function __construct(
    protected Telemetry $model
  ) {}

  /*
    |--------------------------------------------------------------------------
    | Read Operations
    |--------------------------------------------------------------------------
    */

  public function getTrack(string $missionId): Collection
  {
    return collect(DB::select("
            SELECT
                id,
                mission_id,
                recorded_at,
                altitude,
                speed,
                heading,
                metadata,
                ST_AsGeoJSON(position)::json AS position
            FROM telemetry
            WHERE mission_id = ?
            ORDER BY recorded_at ASC
        ", [$missionId]));
  }

  public function getLatestPing(string $missionId): ?Telemetry
  {
    return $this->model
      ->where('mission_id', $missionId)
      ->orderBy('recorded_at', 'desc')
      ->first();
  }

  public function getTrackAsLineString(string $missionId): ?object
  {
    // Return full track as a single GeoJSON LineString
    // Only returns if there are 2+ points (LineString requires minimum 2)
    $result = DB::selectOne("
            SELECT
                ST_AsGeoJSON(
                    ST_MakeLine(
                        position::geometry ORDER BY recorded_at ASC
                    )
                )::json AS linestring,
                COUNT(*) AS point_count,
                MIN(recorded_at) AS started_at,
                MAX(recorded_at) AS ended_at,
                MIN(altitude) AS min_altitude,
                MAX(altitude) AS max_altitude,
                AVG(speed) AS avg_speed
            FROM telemetry
            WHERE mission_id = ?
        ", [$missionId]);

    return $result;
  }

  /*
    |--------------------------------------------------------------------------
    | Write Operations
    |--------------------------------------------------------------------------
    */

  public function create(array $data): Telemetry
  {
    DB::statement("
            INSERT INTO telemetry (
                mission_id, recorded_at, position,
                altitude, speed, heading, metadata,
                created_at
            ) VALUES (
                ?, ?,
                ST_SetSRID(ST_MakePoint(?, ?, ?), 4326),
                ?, ?, ?, ?::jsonb,
                NOW()
            )
        ", [
      $data['mission_id'],
      $data['recorded_at'],
      $data['lng'],        // ST_MakePoint(lng, lat, alt)
      $data['lat'],
      $data['altitude'] ?? 0,
      $data['altitude'] ?? null,
      $data['speed']    ?? null,
      $data['heading']  ?? null,
      json_encode($data['metadata'] ?? []),
    ]);

    return $this->model
      ->where('mission_id', $data['mission_id'])
      ->orderBy('id', 'desc')
      ->first();
  }

  /**
   * Bulk insert telemetry pings in a single transaction.
   * This is the preferred method for high-frequency ingestion.
   */
  public function bulkCreate(array $records): int
  {
    $inserted = 0;

    DB::transaction(function () use ($records, &$inserted) {
      foreach ($records as $data) {
        DB::statement("
                    INSERT INTO telemetry (
                        mission_id, recorded_at, position,
                        altitude, speed, heading, metadata,
                        created_at
                    ) VALUES (
                        ?, ?,
                        ST_SetSRID(ST_MakePoint(?, ?, ?), 4326),
                        ?, ?, ?, ?::jsonb,
                        NOW()
                    )
                ", [
          $data['mission_id'],
          $data['recorded_at'],
          $data['lng'],
          $data['lat'],
          $data['altitude'] ?? 0,
          $data['altitude'] ?? null,
          $data['speed']    ?? null,
          $data['heading']  ?? null,
          json_encode($data['metadata'] ?? []),
        ]);

        $inserted++;
      }
    });

    return $inserted;
  }
}
