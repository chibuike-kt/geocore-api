<?php

// app/Repositories/FeatureRepository.php

namespace App\Repositories;

use App\Models\Feature;
use App\Repositories\Contracts\FeatureRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FeatureRepository implements FeatureRepositoryInterface
{
  public function __construct(
    protected Feature $model
  ) {}

  /*
    |--------------------------------------------------------------------------
    | Read Operations
    |--------------------------------------------------------------------------
    */

  public function findByDataset(string $datasetId, int $perPage = 100): LengthAwarePaginator
  {
    return $this->model
      ->where('dataset_id', $datasetId)
      ->orderBy('created_at', 'desc')
      ->paginate($perPage);
  }

  public function findById(string $id): ?Feature
  {
    return $this->model->find($id);
  }

  public function existsByHash(string $datasetId, string $hash): bool
  {
    return $this->model
      ->where('dataset_id', $datasetId)
      ->where('hash', $hash)
      ->exists();
  }

  public function existsBySourceId(string $datasetId, string $sourceId): bool
  {
    return $this->model
      ->where('dataset_id', $datasetId)
      ->where('source_id', $sourceId)
      ->exists();
  }

  /*
    |--------------------------------------------------------------------------
    | Write Operations
    |--------------------------------------------------------------------------
    */

  public function create(array $data): Feature
  {
    return DB::transaction(function () use ($data) {
      // Use raw SQL to insert geometry via ST_GeomFromText
      // Eloquent cannot handle PostGIS geometry columns natively
      $id = (string) \Illuminate\Support\Str::uuid();

      DB::statement("
                INSERT INTO features (
                    id, dataset_id, name, geometry_type,
                    geometry, properties, source_id, hash,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ST_GeomFromText(?, 4326),
                    ?::jsonb, ?, ?,
                    NOW(), NOW()
                )
            ", [
        $id,
        $data['dataset_id'],
        $data['name'] ?? null,
        $data['geometry_type'],
        $data['geometry_wkt'],
        json_encode($data['properties'] ?? []),
        $data['source_id'] ?? null,
        $data['hash'],
      ]);

      return $this->model->find($id);
    });
  }

  /**
   * Bulk insert features using a single transaction.
   * Returns count of actually inserted rows (skips duplicates).
   */
  public function bulkCreate(string $datasetId, array $features): int
  {
    $inserted = 0;

    DB::transaction(function () use ($datasetId, $features, &$inserted) {
      foreach ($features as $feature) {
        // Skip if hash already exists (idempotent)
        if ($this->existsByHash($datasetId, $feature['hash'])) {
          continue;
        }

        // Skip if source_id already exists
        if (
          !empty($feature['source_id']) &&
          $this->existsBySourceId($datasetId, $feature['source_id'])
        ) {
          continue;
        }

        $id = (string) \Illuminate\Support\Str::uuid();

        DB::statement("
                    INSERT INTO features (
                        id, dataset_id, name, geometry_type,
                        geometry, properties, source_id, hash,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?,
                        ST_GeomFromText(?, 4326),
                        ?::jsonb, ?, ?,
                        NOW(), NOW()
                    )
                ", [
          $id,
          $datasetId,
          $feature['name'] ?? null,
          $feature['geometry_type'],
          $feature['geometry_wkt'],
          json_encode($feature['properties'] ?? []),
          $feature['source_id'] ?? null,
          $feature['hash'],
        ]);

        $inserted++;
      }
    });

    return $inserted;
  }

    /*
    |--------------------------------------------------------------------------
    | Spatial Queries
    |--------------------------------------------------------------------------
    */

  /**
   * Find all features within a polygon (ST_Within).
   */
  public function findWithin(string $datasetId, string $polygonWkt): Collection
  {
    return collect(DB::select("
            SELECT
                id, dataset_id, name, geometry_type, properties, source_id,
                created_at,
                ST_AsGeoJSON(geometry)::json AS geometry
            FROM features
            WHERE dataset_id = ?
              AND ST_Within(geometry, ST_GeomFromText(?, 4326))
        ", [$datasetId, $polygonWkt]));
  }

  /**
   * Find all features within a radius in metres (ST_DWithin).
   * Note: ST_DWithin on geography uses metres directly.
   */
  public function findWithinRadius(string $datasetId, float $lat, float $lng, float $radiusMeters): Collection
  {
    return collect(DB::select("
            SELECT
                id, dataset_id, name, geometry_type, properties, source_id,
                created_at,
                ST_AsGeoJSON(geometry)::json AS geometry,
                ST_Distance(
                    geometry::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) AS distance_meters
            FROM features
            WHERE dataset_id = ?
              AND ST_DWithin(
                    geometry::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                    ?
                  )
            ORDER BY distance_meters ASC
        ", [$lng, $lat, $datasetId, $lng, $lat, $radiusMeters]));
  }

  /**
   * Find the N nearest features to a point (ST_Distance ORDER BY).
   */
  public function findNearest(string $datasetId, float $lat, float $lng, int $limit): Collection
  {
    return collect(DB::select("
            SELECT
                id, dataset_id, name, geometry_type, properties, source_id,
                created_at,
                ST_AsGeoJSON(geometry)::json AS geometry,
                ST_Distance(
                    geometry::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) AS distance_meters
            FROM features
            WHERE dataset_id = ?
            ORDER BY geometry::geography <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
            LIMIT ?
        ", [$lng, $lat, $datasetId, $lng, $lat, $limit]));
  }

  /**
   * Find features that intersect with a given geometry (ST_Intersects).
   */
  public function findIntersecting(string $datasetId, string $geometryWkt): Collection
  {
    return collect(DB::select("
            SELECT
                id, dataset_id, name, geometry_type, properties, source_id,
                created_at,
                ST_AsGeoJSON(geometry)::json AS geometry
            FROM features
            WHERE dataset_id = ?
              AND ST_Intersects(geometry, ST_GeomFromText(?, 4326))
        ", [$datasetId, $geometryWkt]));
  }
}
