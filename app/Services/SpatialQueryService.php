<?php

// app/Services/SpatialQueryService.php

namespace App\Services;

use App\Exceptions\InvalidGeometryException;
use App\Geo\GeometryHelper;
use App\Models\AuditLog;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use App\Repositories\Contracts\FeatureRepositoryInterface;
use Illuminate\Support\Collection;

class SpatialQueryService
{
  public function __construct(
    protected FeatureRepositoryInterface $featureRepository,
    protected DatasetRepositoryInterface $datasetRepository,
  ) {}

  /*
    |--------------------------------------------------------------------------
    | ST_Within — features completely inside a polygon
    |--------------------------------------------------------------------------
    */

  public function within(string $datasetId, array $polygonGeometry): Collection
  {
    $this->assertDatasetExists($datasetId);

    GeometryHelper::validate($polygonGeometry);

    if ($polygonGeometry['type'] !== 'Polygon' && $polygonGeometry['type'] !== 'MultiPolygon') {
      throw new InvalidGeometryException(
        'ST_Within query requires a Polygon or MultiPolygon geometry.'
      );
    }

    $wkt     = GeometryHelper::toWkt($polygonGeometry);
    $results = $this->featureRepository->findWithin($datasetId, $wkt);

    AuditLog::record(
      entityType: 'dataset',
      entityId: $datasetId,
      action: 'query_within',
      payload: ['result_count' => $results->count()],
    );

    return $results;
  }

  /*
    |--------------------------------------------------------------------------
    | ST_DWithin — features within a radius in metres
    |--------------------------------------------------------------------------
    */

  public function radius(
    string $datasetId,
    float  $lat,
    float  $lng,
    float  $radiusMeters
  ): Collection {
    $this->assertDatasetExists($datasetId);
    $this->validateCoordinates($lat, $lng);
    $this->validateRadius($radiusMeters);

    $results = $this->featureRepository->findWithinRadius(
      $datasetId,
      $lat,
      $lng,
      $radiusMeters
    );

    AuditLog::record(
      entityType: 'dataset',
      entityId: $datasetId,
      action: 'query_radius',
      payload: [
        'lat'          => $lat,
        'lng'          => $lng,
        'radius_m'     => $radiusMeters,
        'result_count' => $results->count(),
      ],
    );

    return $results;
  }

  /*
    |--------------------------------------------------------------------------
    | ST_Distance ORDER BY — N nearest features to a point
    |--------------------------------------------------------------------------
    */

  public function nearest(
    string $datasetId,
    float  $lat,
    float  $lng,
    int    $limit
  ): Collection {
    $this->assertDatasetExists($datasetId);
    $this->validateCoordinates($lat, $lng);

    $limit   = min($limit, config('geocore.query.max_limit', 1000));
    $results = $this->featureRepository->findNearest($datasetId, $lat, $lng, $limit);

    AuditLog::record(
      entityType: 'dataset',
      entityId: $datasetId,
      action: 'query_nearest',
      payload: [
        'lat'          => $lat,
        'lng'          => $lng,
        'limit'        => $limit,
        'result_count' => $results->count(),
      ],
    );

    return $results;
  }

  /*
    |--------------------------------------------------------------------------
    | ST_Intersects — features that intersect any geometry
    |--------------------------------------------------------------------------
    */

  public function intersects(string $datasetId, array $geometry): Collection
  {
    $this->assertDatasetExists($datasetId);

    GeometryHelper::validate($geometry);

    $wkt     = GeometryHelper::toWkt($geometry);
    $results = $this->featureRepository->findIntersecting($datasetId, $wkt);

    AuditLog::record(
      entityType: 'dataset',
      entityId: $datasetId,
      action: 'query_intersects',
      payload: [
        'geometry_type' => $geometry['type'],
        'result_count'  => $results->count(),
      ],
    );

    return $results;
  }

  /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

  private function assertDatasetExists(string $datasetId): void
  {
    if (!$this->datasetRepository->findById($datasetId)) {
      throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
        "Dataset [{$datasetId}] not found."
      );
    }
  }

  private function validateCoordinates(float $lat, float $lng): void
  {
    if ($lat < -90 || $lat > 90) {
      throw new InvalidGeometryException(
        "Latitude [{$lat}] must be between -90 and 90."
      );
    }

    if ($lng < -180 || $lng > 180) {
      throw new InvalidGeometryException(
        "Longitude [{$lng}] must be between -180 and 180."
      );
    }
  }

  private function validateRadius(float $radiusMeters): void
  {
    $max = config('geocore.query.max_radius_meters', 50000);

    if ($radiusMeters <= 0) {
      throw new \InvalidArgumentException('Radius must be greater than 0.');
    }

    if ($radiusMeters > $max) {
      throw new \InvalidArgumentException(
        "Radius [{$radiusMeters}m] exceeds maximum allowed [{$max}m]."
      );
    }
  }
}
