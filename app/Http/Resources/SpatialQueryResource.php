<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpatialQueryResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    $resource = $this->resource;
    return [
      'id'            => $resource->id,
      'dataset_id'    => $resource->dataset_id,
      'name'          => $resource->name,
      'geometry_type' => $resource->geometry_type,
      'geometry'      => $this->resolveGeometry($resource),
      'properties'    => $this->resolveProperties($resource),
      'source_id'     => $resource->source_id,
      'distance_meters' => isset($resource->distance_meters) ?      (float)$resource->distance_meters : null,
      'created_at'    => $resource->created_at,
    ];
  }

  private function resolveGeometry($resource): mixed
  {
    // If geometry is already decoded (raw query result), return it directly
    if (!isset($resource->geometry)){
      return null;
    }

    // Raw DB result returns geometry as a JSON string or object
    if (is_string($resource->geometry)) {
      return json_decode($resource->geometry, true);
    }

    return $resource->geometry;
  }

  private function resolveProperties(object $resource): array
  {
    if (!isset($resource->properties)) {
      return [];
    }

    if (is_string($resource->properties)) {
      return json_decode($resource->properties, true) ?? [];
    }

    if (is_array($resource->properties)) {
      return $resource->properties;
    }

    return [];
  }
}
