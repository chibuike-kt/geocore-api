<?php
// app/Models/Feature.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use TarfinLabs\LaravelSpatial\Traits\HasSpatial;
use TarfinLabs\LaravelSpatial\Types\Point;
use TarfinLabs\LaravelSpatial\Types\LineString;
use TarfinLabs\LaravelSpatial\Types\Polygon;

class Feature extends BaseModel
{
  use HasFactory;
  use HasSpatial;

  protected $fillable = [
    'dataset_id',
    'name',
    'geometry_type',
    'geometry',
    'properties',
    'source_id',
    'hash',
  ];

  protected $casts = [
    'properties' => 'array',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  /**
   * Geometry column declared for the HasSpatial trait.
   */
  protected array $spatialFields = ['geometry'];

  /**
   * Valid geometry types.
   */
  const GEOMETRY_TYPES = [
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
    | Relationships
    |--------------------------------------------------------------------------
    */

  public function dataset(): BelongsTo
  {
    return $this->belongsTo(Dataset::class);
  }

  /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

  public function scopeOfType($query, string $geometryType)
  {
    return $query->where('geometry_type', $geometryType);
  }

  public function scopeInDataset($query, string $datasetId)
  {
    return $query->where('dataset_id', $datasetId);
  }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

  /**
   * Return geometry as a GeoJSON array.
   */
  public function toGeoJsonFeature(): array
  {
    return [
      'type'       => 'Feature',
      'id'         => $this->id,
      'geometry'   => json_decode($this->getRawGeometry(), true),
      'properties' => array_merge($this->properties ?? [], [
        'name'         => $this->name,
        'dataset_id'   => $this->dataset_id,
        'geometry_type' => $this->geometry_type,
        'created_at'   => $this->created_at?->toISOString(),
      ]),
    ];
  }

  /**
   * Get raw geometry as GeoJSON string from PostGIS.
   */
  public function getRawGeometry(): string
  {
    $result = \DB::selectOne(
      'SELECT ST_AsGeoJSON(geometry) as geojson FROM features WHERE id = ?',
      [$this->id]
    );

    return $result?->geojson ?? '{}';
  }
}
