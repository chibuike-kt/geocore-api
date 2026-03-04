<?php

// app/Http/Resources/FeatureResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class FeatureResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id'            => $this->id,
      'dataset_id'    => $this->dataset_id,
      'name'          => $this->name,
      'geometry_type' => $this->geometry_type,
      'geometry'      => $this->resolveGeometry(),
      'properties'    => $this->properties ?? [],
      'source_id'     => $this->source_id,
      'created_at'    => $this->created_at?->toISOString(),
      'updated_at'    => $this->updated_at?->toISOString(),
    ];
  }

  /**
   * Resolve geometry as GeoJSON.
   * Handles both Eloquent model instances and raw DB result objects.
   */
  private function resolveGeometry(): mixed
  {
    // Raw query result already has geometry as decoded JSON
    if (isset($this->resource->geometry) && is_object($this->resource->geometry)) {
      return $this->resource->geometry;
    }

    // Eloquent model — fetch geometry from PostGIS as GeoJSON
    if ($this->id) {
      $result = DB::selectOne(
        'SELECT ST_AsGeoJSON(geometry)::json AS geometry FROM features WHERE id = ?',
        [$this->id]
      );
      return $result?->geometry ? json_decode($result->geometry, true) : null;
    }

    return null;
  }
}
