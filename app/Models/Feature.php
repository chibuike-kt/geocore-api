<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// Remove the HasSpatial import entirely

class Feature extends BaseModel
{
  use HasFactory;
  // Remove: use HasSpatial;

  protected $fillable = [
    'dataset_id',
    'name',
    'geometry_type',
    'properties',
    'source_id',
    'hash',
    // Remove 'geometry' from fillable — we handle it via raw SQL
  ];

  protected $casts = [
    'properties' => 'array',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  // Remove the $spatialFields property entirely

  const GEOMETRY_TYPES = [
    'Point',
    'LineString',
    'Polygon',
    'MultiPoint',
    'MultiLineString',
    'MultiPolygon',
    'GeometryCollection',
  ];

  public function dataset(): BelongsTo
  {
    return $this->belongsTo(Dataset::class);
  }

  public function scopeOfType($query, string $geometryType)
  {
    return $query->where('geometry_type', $geometryType);
  }

  public function scopeInDataset($query, string $datasetId)
  {
    return $query->where('dataset_id', $datasetId);
  }
}
