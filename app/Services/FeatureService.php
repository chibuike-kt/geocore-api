<?php

// app/Services/FeatureService.php

namespace App\Services;

use App\Exceptions\InvalidGeometryException;
use App\Geo\GeometryHelper;
use App\Models\AuditLog;
use App\Models\Feature;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use App\Repositories\Contracts\FeatureRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FeatureService
{
  public function __construct(
    protected FeatureRepositoryInterface $repository,
    protected DatasetRepositoryInterface $datasetRepository,
  ) {}

  /*
    |--------------------------------------------------------------------------
    | List
    |--------------------------------------------------------------------------
    */

  public function list(string $datasetId, int $perPage = 100): LengthAwarePaginator
  {
    $this->assertDatasetExists($datasetId);

    return $this->repository->findByDataset($datasetId, $perPage);
  }

  /*
    |--------------------------------------------------------------------------
    | Single Feature Ingestion
    |--------------------------------------------------------------------------
    */

  public function create(string $datasetId, array $data): Feature
  {
    $this->assertDatasetExists($datasetId);

    $geometry = $data['geometry'];

    // Validate geometry
    GeometryHelper::validate($geometry);

    // Build hash for idempotency check
    $hash = GeometryHelper::hash($geometry);

    // Idempotency check — skip if same geometry already exists
    if ($this->repository->existsByHash($datasetId, $hash)) {
      return $this->repository->findByDataset($datasetId, 1)->first();
    }

    // Convert GeoJSON geometry to WKT for PostGIS
    $wkt = GeometryHelper::toWkt($geometry);

    $feature = $this->repository->create([
      'dataset_id'    => $datasetId,
      'name'          => $data['name'] ?? null,
      'geometry_type' => $geometry['type'],
      'geometry_wkt'  => $wkt,
      'properties'    => $data['properties'] ?? [],
      'source_id'     => $data['source_id'] ?? null,
      'hash'          => $hash,
    ]);

    AuditLog::record(
      entityType: 'feature',
      entityId: $feature->id,
      action: 'created',
      payload: [
        'dataset_id'    => $datasetId,
        'geometry_type' => $geometry['type'],
      ],
    );

    return $feature;
  }

    /*
    |--------------------------------------------------------------------------
    | Bulk GeoJSON Ingestion
    |--------------------------------------------------------------------------
    */

  /**
   * Accept a GeoJSON FeatureCollection and ingest all features.
   * Skips duplicates. Returns ingestion summary.
   */
  public function bulkCreate(string $datasetId, array $geojson): array
  {
    $this->assertDatasetExists($datasetId);

    if (($geojson['type'] ?? '') !== 'FeatureCollection') {
      throw new \InvalidArgumentException(
        'Bulk upload requires a GeoJSON FeatureCollection.'
      );
    }

    $features     = $geojson['features'] ?? [];
    $maxFeatures  = config('geocore.max_bulk_features', 1000);

    if (count($features) > $maxFeatures) {
      throw new \InvalidArgumentException(
        "Bulk upload exceeds maximum of {$maxFeatures} features per request."
      );
    }

    $prepared = [];
    $errors   = [];

    foreach ($features as $index => $feature) {
      try {
        $geometry = $feature['geometry'] ?? null;

        if (!$geometry) {
          $errors[] = "Feature at index {$index} has no geometry.";
          continue;
        }

        GeometryHelper::validate($geometry);

        $prepared[] = [
          'name'          => $feature['properties']['name'] ?? null,
          'geometry_type' => $geometry['type'],
          'geometry_wkt'  => GeometryHelper::toWkt($geometry),
          'properties'    => $feature['properties'] ?? [],
          'source_id'     => $feature['id'] ?? $feature['properties']['source_id'] ?? null,
          'hash'          => GeometryHelper::hash($geometry),
        ];
      } catch (InvalidGeometryException $e) {
        $errors[] = "Feature at index {$index}: {$e->getMessage()}";
      }
    }

    $inserted = $this->repository->bulkCreate($datasetId, $prepared);

    AuditLog::record(
      entityType: 'dataset',
      entityId: $datasetId,
      action: 'bulk_ingestion',
      payload: [
        'submitted' => count($features),
        'prepared'  => count($prepared),
        'inserted'  => $inserted,
        'skipped'   => count($prepared) - $inserted,
        'errors'    => count($errors),
      ],
    );

    return [
      'submitted' => count($features),
      'inserted'  => $inserted,
      'skipped'   => count($prepared) - $inserted,
      'errors'    => $errors,
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

  private function assertDatasetExists(string $datasetId): void
  {
    $dataset = $this->datasetRepository->findById($datasetId);

    if (!$dataset) {
      throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
        "Dataset [{$datasetId}] not found."
      );
    }
  }
}
