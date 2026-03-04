<?php

// app/Repositories/AnalyticsRepository.php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class AnalyticsRepository
{
  /*
    |--------------------------------------------------------------------------
    | Buffer Analysis
    |--------------------------------------------------------------------------
    |
    | ST_Buffer expands a geometry by a given distance.
    | We cast to geography for metre-accurate buffering,
    | then cast back to geometry for return.
    |
    */

  public function buffer(string $geometryWkt, float $radiusMeters): object
  {
    $result = DB::selectOne("
            SELECT
                ST_AsGeoJSON(
                    ST_Buffer(
                        ST_GeomFromText(?, 4326)::geography,
                        ?
                    )::geometry
                )::json AS geometry,
                ST_Area(
                    ST_Buffer(
                        ST_GeomFromText(?, 4326)::geography,
                        ?
                    )
                ) AS area_sqm
        ", [$geometryWkt, $radiusMeters, $geometryWkt, $radiusMeters]);

    return $result;
  }

  /**
   * Buffer and return features that fall inside the buffered zone.
   */
  public function bufferWithFeatures(
    string $geometryWkt,
    float  $radiusMeters,
    string $datasetId
  ): object {
    $buffered = DB::selectOne("
            SELECT ST_AsGeoJSON(
                ST_Buffer(
                    ST_GeomFromText(?, 4326)::geography,
                    ?
                )::geometry
            )::json AS geometry
        ", [$geometryWkt, $radiusMeters]);

    $features = DB::select("
            SELECT
                id, name, geometry_type, properties,
                ST_AsGeoJSON(geometry)::json AS geometry,
                ST_Distance(
                    geometry::geography,
                    ST_GeomFromText(?, 4326)::geography
                ) AS distance_meters
            FROM features
            WHERE dataset_id = ?
              AND ST_DWithin(
                    geometry::geography,
                    ST_GeomFromText(?, 4326)::geography,
                    ?
                  )
            ORDER BY distance_meters ASC
        ", [$geometryWkt, $datasetId, $geometryWkt, $radiusMeters]);

    return (object) [
      'buffer'   => $buffered,
      'features' => $features,
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Coverage Analysis
    |--------------------------------------------------------------------------
    |
    | Calculates the total area covered by all features in a dataset,
    | the union geometry, and coverage percentage within a boundary.
    |
    */

  public function coverage(string $datasetId, ?string $boundaryWkt = null): object
  {
    // Total area of all polygon features in the dataset
    $result = DB::selectOne("
            SELECT
                COUNT(*) AS feature_count,
                COUNT(*) FILTER (WHERE geometry_type = 'Polygon') AS polygon_count,
                COALESCE(
                    ST_Area(ST_Union(geometry)::geography),
                    0
                ) AS covered_area_sqm,
                ST_AsGeoJSON(ST_Union(geometry))::json AS union_geometry
            FROM features
            WHERE dataset_id = ?
              AND geometry_type IN ('Polygon', 'MultiPolygon')
        ", [$datasetId]);

    // If a boundary is provided, calculate coverage percentage
    $coveragePercent = null;
    $boundaryAreaSqm = null;

    if ($boundaryWkt) {
      $boundary = DB::selectOne("
                SELECT
                    ST_Area(ST_GeomFromText(?, 4326)::geography) AS boundary_area_sqm,
                    ST_AsGeoJSON(
                        ST_Intersection(
                            ST_Union(geometry),
                            ST_GeomFromText(?, 4326)
                        )
                    )::json AS intersection_geometry,
                    ST_Area(
                        ST_Intersection(
                            ST_Union(geometry),
                            ST_GeomFromText(?, 4326)
                        )::geography
                    ) AS intersection_area_sqm
                FROM features
                WHERE dataset_id = ?
                  AND geometry_type IN ('Polygon', 'MultiPolygon')
                  AND ST_Intersects(geometry, ST_GeomFromText(?, 4326))
            ", [
        $boundaryWkt,
        $boundaryWkt,
        $boundaryWkt,
        $datasetId,
        $boundaryWkt,
      ]);

      if ($boundary && $boundary->boundary_area_sqm > 0) {
        $boundaryAreaSqm = (float) $boundary->boundary_area_sqm;
        $coveragePercent = round(
          ((float) $boundary->intersection_area_sqm / $boundaryAreaSqm) * 100,
          4
        );
      }
    }

    return (object) [
      'feature_count'    => (int) $result->feature_count,
      'polygon_count'    => (int) $result->polygon_count,
      'covered_area_sqm' => round((float) $result->covered_area_sqm, 4),
      'covered_area_km2' => round((float) $result->covered_area_sqm / 1_000_000, 6),
      'union_geometry'   => $result->union_geometry,
      'boundary_area_sqm' => $boundaryAreaSqm,
      'coverage_percent' => $coveragePercent,
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Density Analysis
    |--------------------------------------------------------------------------
    |
    | Counts features per cell in a grid overlaid on the dataset's extent.
    | Uses ST_SquareGrid to generate the grid cells, then counts features
    | per cell using ST_Intersects.
    |
    */

  public function density(string $datasetId, float $cellSizeMeters): array
  {
    // Use ST_SquareGrid to generate a grid over the dataset extent
    // then count features intersecting each cell
    $results = DB::select("
            WITH dataset_extent AS (
                SELECT ST_Extent(geometry) AS extent
                FROM features
                WHERE dataset_id = ?
            ),
            grid AS (
                SELECT (ST_SquareGrid(
                    ?,
                    ST_Transform(
                        ST_SetSRID(extent::geometry, 4326),
                        4326
                    )
                )).*
                FROM dataset_extent
                WHERE extent IS NOT NULL
            )
            SELECT
                ST_AsGeoJSON(grid.geom)::json AS cell_geometry,
                COUNT(f.id) AS feature_count,
                ST_AsGeoJSON(ST_Centroid(grid.geom))::json AS centroid
            FROM grid
            LEFT JOIN features f
                ON f.dataset_id = ?
                AND ST_Intersects(f.geometry, grid.geom)
            GROUP BY grid.geom
            HAVING COUNT(f.id) > 0
            ORDER BY feature_count DESC
        ", [$datasetId, $cellSizeMeters, $datasetId]);

    return $results;
  }

  /*
    |--------------------------------------------------------------------------
    | Cluster Analysis
    |--------------------------------------------------------------------------
    |
    | Uses ST_ClusterDBSCAN to group nearby point features into clusters.
    | Returns cluster ID and member count per cluster.
    |
    */

  public function cluster(
    string $datasetId,
    float  $radiusMeters,
    int    $minPoints = 2
  ): array {
    $results = DB::select("
            WITH clustered AS (
                SELECT
                    id,
                    name,
                    properties,
                    ST_AsGeoJSON(geometry)::json AS geometry,
                    ST_ClusterDBSCAN(geometry, ?, ?)
                        OVER () AS cluster_id
                FROM features
                WHERE dataset_id = ?
                  AND geometry_type = 'Point'
            )
            SELECT
                cluster_id,
                COUNT(*) AS member_count,
                ST_AsGeoJSON(
                    ST_Centroid(
                        ST_Collect(geometry::geometry)
                    )
                )::json AS cluster_centroid,
                json_agg(json_build_object(
                    'id',         id,
                    'name',       name,
                    'geometry',   geometry,
                    'properties', properties
                )) AS members
            FROM clustered
            WHERE cluster_id IS NOT NULL
            GROUP BY cluster_id
            ORDER BY member_count DESC
        ", [
      // ST_ClusterDBSCAN expects degrees for geometry type
      // Convert metres to approximate degrees (1 degree ≈ 111km)
      $radiusMeters / 111000,
      $minPoints,
      $datasetId,
    ]);

    return $results;
  }

  /*
    |--------------------------------------------------------------------------
    | Extent
    |--------------------------------------------------------------------------
    | Returns the bounding box of all features in a dataset.
    */

  public function extent(string $datasetId): ?object
  {
    return DB::selectOne("
            SELECT
                ST_AsGeoJSON(ST_Extent(geometry))::json AS bbox_geometry,
                ST_XMin(ST_Extent(geometry)) AS min_lng,
                ST_YMin(ST_Extent(geometry)) AS min_lat,
                ST_XMax(ST_Extent(geometry)) AS max_lng,
                ST_YMax(ST_Extent(geometry)) AS max_lat
            FROM features
            WHERE dataset_id = ?
        ", [$datasetId]);
  }
}
