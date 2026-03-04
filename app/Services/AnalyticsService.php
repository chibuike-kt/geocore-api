<?php

// app/Services/AnalyticsService.php

namespace App\Services;

use App\Exceptions\InvalidGeometryException;
use App\Geo\GeometryHelper;
use App\Models\AuditLog;
use App\Repositories\AnalyticsRepository;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AnalyticsService
{
  public function __construct(
    protected AnalyticsRepository        $analyticsRepository,
    protected DatasetRepositoryInterface $datasetRepository,
  ) {}

  /*
    |--------------------------------------------------------------------------
    | Buffer Analysis
    |--------------------------------------------------------------------------
    */

  public function buffer(array $data): array
  {
    $geometry     = $data['geometry'];
    $radiusMeters = (float) $data['radius'];
    $datasetId    = $data['dataset_id'] ?? null;

    GeometryHelper::validate($geometry);

    $wkt = GeometryHelper::toWkt($geometry);

    if ($datasetId) {
      $this->assertDatasetExists($datasetId);
      $result = $this->analyticsRepository->bufferWithFeatures(
        $wkt,
        $radiusMeters,
        $datasetId
      );

      return [
        'type'          => 'buffer',
        'radius_meters' => $radiusMeters,
        'buffer'        => [
          'type'     => 'Feature',
          'geometry' => $result->buffer->geometry,
        ],
        'features_inside' => array_map(
          fn($f) => [
            'id'             => $f->id,
            'name'           => $f->name,
            'geometry_type'  => $f->geometry_type,
            'geometry'       => $f->geometry,
            'properties'     => is_string($f->properties)
              ? json_decode($f->properties, true)
              : $f->properties,
            'distance_meters' => round((float) $f->distance_meters, 2),
          ],
          $result->features
        ),
        'feature_count' => count($result->features),
      ];
    }

    // No dataset — just return the buffer geometry
    $result = $this->analyticsRepository->buffer($wkt, $radiusMeters);

    return [
      'type'          => 'buffer',
      'radius_meters' => $radiusMeters,
      'area_sqm'      => round((float) $result->area_sqm, 4),
      'area_km2'      => round((float) $result->area_sqm / 1_000_000, 6),
      'buffer'        => [
        'type'     => 'Feature',
        'geometry' => $result->geometry,
      ],
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Coverage Analysis
    |--------------------------------------------------------------------------
    */

  public function coverage(string $datasetId, ?array $boundary = null): array
  {
    $this->assertDatasetExists($datasetId);

    $boundaryWkt = null;

    if ($boundary) {
      GeometryHelper::validate($boundary);

      if (!in_array($boundary['type'], ['Polygon', 'MultiPolygon'])) {
        throw new InvalidGeometryException(
          'Coverage boundary must be a Polygon or MultiPolygon.'
        );
      }

      $boundaryWkt = GeometryHelper::toWkt($boundary);
    }

    $result = $this->analyticsRepository->coverage($datasetId, $boundaryWkt);

    AuditLog::record(
      entityType: 'dataset',
      entityId: $datasetId,
      action: 'analytics_coverage',
      payload: ['covered_area_km2' => $result->covered_area_km2],
    );

    return [
      'type'             => 'coverage',
      'dataset_id'       => $datasetId,
      'feature_count'    => $result->feature_count,
      'polygon_count'    => $result->polygon_count,
      'covered_area_sqm' => $result->covered_area_sqm,
      'covered_area_km2' => $result->covered_area_km2,
      'coverage_percent' => $result->coverage_percent,
      'boundary_area_sqm' => $result->boundary_area_sqm,
      'union_geometry'   => $result->union_geometry
        ? ['type' => 'Feature', 'geometry' => $result->union_geometry]
        : null,
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Density Analysis
    |--------------------------------------------------------------------------
    */

  public function density(string $datasetId, float $cellSizeMeters): array
  {
    $this->assertDatasetExists($datasetId);

    // Enforce sensible cell size limits
    if ($cellSizeMeters < 10) {
      throw new \InvalidArgumentException(
        'Cell size must be at least 10 metres.'
      );
    }

    if ($cellSizeMeters > 100000) {
      throw new \InvalidArgumentException(
        'Cell size cannot exceed 100,000 metres (100km).'
      );
    }

    $cells = $this->analyticsRepository->density($datasetId, $cellSizeMeters);

    // Format as GeoJSON FeatureCollection
    $features = array_map(fn($cell) => [
      'type'     => 'Feature',
      'geometry' => $cell->cell_geometry,
      'properties' => [
        'feature_count' => (int) $cell->feature_count,
        'centroid'      => $cell->centroid,
      ],
    ], $cells);

    AuditLog::record(
      entityType: 'dataset',
      entityId: $datasetId,
      action: 'analytics_density',
      payload: [
        'cell_size_meters' => $cellSizeMeters,
        'cell_count'       => count($cells),
      ],
    );

    return [
      'type'             => 'density',
      'dataset_id'       => $datasetId,
      'cell_size_meters' => $cellSizeMeters,
      'cell_count'       => count($cells),
      'result'           => [
        'type'     => 'FeatureCollection',
        'features' => $features,
      ],
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Cluster Analysis
    |--------------------------------------------------------------------------
    */

  public function cluster(
    string $datasetId,
    float  $radiusMeters,
    int    $minPoints
  ): array {
    $this->assertDatasetExists($datasetId);

    $clusters = $this->analyticsRepository->cluster(
      $datasetId,
      $radiusMeters,
      $minPoints
    );

    AuditLog::record(
      entityType: 'dataset',
      entityId: $datasetId,
      action: 'analytics_cluster',
      payload: [
        'radius_meters' => $radiusMeters,
        'min_points'    => $minPoints,
        'cluster_count' => count($clusters),
      ],
    );

    return [
      'type'          => 'cluster',
      'dataset_id'    => $datasetId,
      'radius_meters' => $radiusMeters,
      'min_points'    => $minPoints,
      'cluster_count' => count($clusters),
      'clusters'      => array_map(fn($c) => [
        'cluster_id'      => $c->cluster_id,
        'member_count'    => (int) $c->member_count,
        'centroid'        => $c->cluster_centroid,
        'members'         => is_string($c->members)
          ? json_decode($c->members, true)
          : $c->members,
      ], $clusters),
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Extent
    |--------------------------------------------------------------------------
    */

  public function extent(string $datasetId): array
  {
    $this->assertDatasetExists($datasetId);

    $result = $this->analyticsRepository->extent($datasetId);

    if (!$result || !$result->bbox_geometry) {
      return [
        'dataset_id' => $datasetId,
        'extent'     => null,
        'message'    => 'Dataset has no features.',
      ];
    }

    return [
      'type'       => 'extent',
      'dataset_id' => $datasetId,
      'bbox'       => [
        'min_lat' => (float) $result->min_lat,
        'min_lng' => (float) $result->min_lng,
        'max_lat' => (float) $result->max_lat,
        'max_lng' => (float) $result->max_lng,
      ],
      'geometry' => [
        'type'     => 'Feature',
        'geometry' => $result->bbox_geometry,
      ],
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

  private function assertDatasetExists(string $datasetId): void
  {
    if (!$this->datasetRepository->findById($datasetId)) {
      throw new ModelNotFoundException(
        "Dataset [{$datasetId}] not found."
      );
    }
  }
}
