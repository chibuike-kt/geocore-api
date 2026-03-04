<?php

// app/Geo/GeometryHelper.php

namespace App\Geo;

use App\Exceptions\InvalidGeometryException;

class GeometryHelper
{
  const SUPPORTED_TYPES = [
    'Point',
    'LineString',
    'Polygon',
    'MultiPoint',
    'MultiLineString',
    'MultiPolygon',
    'GeometryCollection',
  ];

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

  /**
   * Validate a GeoJSON geometry array.
   * Throws InvalidGeometryException on failure.
   */
  public static function validate(array $geometry): void
  {
    if (empty($geometry['type'])) {
      throw new InvalidGeometryException('Geometry must have a type field.');
    }

    if (!in_array($geometry['type'], self::SUPPORTED_TYPES)) {
      throw new InvalidGeometryException(
        "Unsupported geometry type [{$geometry['type']}]. " .
          "Supported: " . implode(', ', self::SUPPORTED_TYPES)
      );
    }

    if ($geometry['type'] !== 'GeometryCollection' && empty($geometry['coordinates'])) {
      throw new InvalidGeometryException('Geometry must have a coordinates field.');
    }

    match ($geometry['type']) {
      'Point'           => self::validatePoint($geometry['coordinates']),
      'LineString'      => self::validateLineString($geometry['coordinates']),
      'Polygon'         => self::validatePolygon($geometry['coordinates']),
      'MultiPoint'      => self::validateMultiPoint($geometry['coordinates']),
      'MultiLineString' => self::validateMultiLineString($geometry['coordinates']),
      'MultiPolygon'    => self::validateMultiPolygon($geometry['coordinates']),
      default           => null,
    };
  }

  /**
   * Validate a Point coordinate pair [lng, lat].
   */
  private static function validatePoint(array $coords): void
  {
    if (count($coords) < 2) {
      throw new InvalidGeometryException('Point must have at least [longitude, latitude].');
    }

    [$lng, $lat] = $coords;

    if ($lng < -180 || $lng > 180) {
      throw new InvalidGeometryException("Longitude [{$lng}] must be between -180 and 180.");
    }

    if ($lat < -90 || $lat > 90) {
      throw new InvalidGeometryException("Latitude [{$lat}] must be between -90 and 90.");
    }
  }

  /**
   * Validate a LineString — must have at least 2 points.
   */
  private static function validateLineString(array $coords): void
  {
    if (count($coords) < 2) {
      throw new InvalidGeometryException('LineString must have at least 2 coordinate pairs.');
    }

    foreach ($coords as $point) {
      self::validatePoint($point);
    }
  }

  /**
   * Validate a Polygon — must have at least one ring,
   * each ring must have at least 4 points and be closed.
   */
  private static function validatePolygon(array $coords): void
  {
    if (empty($coords)) {
      throw new InvalidGeometryException('Polygon must have at least one ring.');
    }

    foreach ($coords as $ring) {
      if (count($ring) < 4) {
        throw new InvalidGeometryException(
          'Each Polygon ring must have at least 4 positions.'
        );
      }

      $first = $ring[0];
      $last  = $ring[count($ring) - 1];

      if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
        throw new InvalidGeometryException(
          'Polygon ring must be closed — first and last position must be identical.'
        );
      }

      foreach ($ring as $point) {
        self::validatePoint($point);
      }
    }
  }

  private static function validateMultiPoint(array $coords): void
  {
    foreach ($coords as $point) {
      self::validatePoint($point);
    }
  }

  private static function validateMultiLineString(array $coords): void
  {
    foreach ($coords as $line) {
      self::validateLineString($line);
    }
  }

  private static function validateMultiPolygon(array $coords): void
  {
    foreach ($coords as $polygon) {
      self::validatePolygon($polygon);
    }
  }

    /*
    |--------------------------------------------------------------------------
    | Conversion
    |--------------------------------------------------------------------------
    */

  /**
   * Convert a GeoJSON geometry array to WKT string.
   * Used for passing to PostGIS functions like ST_GeomFromText().
   */
  public static function toWkt(array $geometry): string
  {
    return match ($geometry['type']) {
      'Point'           => self::pointToWkt($geometry['coordinates']),
      'LineString'      => self::lineStringToWkt($geometry['coordinates']),
      'Polygon'         => self::polygonToWkt($geometry['coordinates']),
      'MultiPoint'      => self::multiPointToWkt($geometry['coordinates']),
      'MultiLineString' => self::multiLineStringToWkt($geometry['coordinates']),
      'MultiPolygon'    => self::multiPolygonToWkt($geometry['coordinates']),
      default           => throw new InvalidGeometryException(
        "Cannot convert [{$geometry['type']}] to WKT."
      ),
    };
  }

  private static function pointToWkt(array $coords): string
  {
    $parts = implode(' ', $coords);
    return "POINT({$parts})";
  }

  private static function lineStringToWkt(array $coords): string
  {
    $points = implode(', ', array_map(fn($c) => implode(' ', $c), $coords));
    return "LINESTRING({$points})";
  }

  private static function polygonToWkt(array $coords): string
  {
    $rings = array_map(function ($ring) {
      $points = implode(', ', array_map(fn($c) => implode(' ', $c), $ring));
      return "({$points})";
    }, $coords);

    return 'POLYGON(' . implode(', ', $rings) . ')';
  }

  private static function multiPointToWkt(array $coords): string
  {
    $points = implode(', ', array_map(fn($c) => '(' . implode(' ', $c) . ')', $coords));
    return "MULTIPOINT({$points})";
  }

  private static function multiLineStringToWkt(array $coords): string
  {
    $lines = array_map(function ($line) {
      $points = implode(', ', array_map(fn($c) => implode(' ', $c), $line));
      return "({$points})";
    }, $coords);

    return 'MULTILINESTRING(' . implode(', ', $lines) . ')';
  }

  private static function multiPolygonToWkt(array $coords): string
  {
    $polygons = array_map(function ($polygon) {
      $rings = array_map(function ($ring) {
        $points = implode(', ', array_map(fn($c) => implode(' ', $c), $ring));
        return "({$points})";
      }, $polygon);
      return '(' . implode(', ', $rings) . ')';
    }, $coords);

    return 'MULTIPOLYGON(' . implode(', ', $polygons) . ')';
  }

    /*
    |--------------------------------------------------------------------------
    | Hashing
    |--------------------------------------------------------------------------
    */

  /**
   * Generate a deterministic SHA-256 hash of a geometry.
   * Used for idempotent ingestion — same geometry = same hash.
   */
  public static function hash(array $geometry): string
  {
    // Normalize to JSON with sorted keys for deterministic output
    $normalized = json_encode([
      'type'        => $geometry['type'],
      'coordinates' => $geometry['coordinates'] ?? null,
    ]);

    return hash('sha256', $normalized);
  }

    /*
    |--------------------------------------------------------------------------
    | GeoJSON Construction
    |--------------------------------------------------------------------------
    */

  /**
   * Wrap a geometry in a GeoJSON Feature envelope.
   */
  public static function toFeature(array $geometry, array $properties = [], ?string $id = null): array
  {
    $feature = [
      'type'       => 'Feature',
      'geometry'   => $geometry,
      'properties' => $properties,
    ];

    if ($id) {
      $feature['id'] = $id;
    }

    return $feature;
  }

  /**
   * Wrap features in a GeoJSON FeatureCollection envelope.
   */
  public static function toFeatureCollection(array $features): array
  {
    return [
      'type'     => 'FeatureCollection',
      'features' => $features,
    ];
  }
}
