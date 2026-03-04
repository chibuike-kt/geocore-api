<?php

// app/Http/Resources/GeofenceResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class GeofenceResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id'           => $this->id,
      'name'         => $this->name,
      'type'         => $this->type,
      'geometry'     => $this->resolveGeometry(),
      'altitude_min' => $this->altitude_min,
      'altitude_max' => $this->altitude_max,
      'active'       => $this->active,
      'metadata'     => $this->metadata ?? [],
      'created_at'   => $this->created_at?->toISOString(),
      'updated_at'   => $this->updated_at?->toISOString(),
    ];
  }

  private function resolveGeometry(): mixed
  {
    if (!$this->id) {
      return null;
    }

    $result = DB::selectOne(
      'SELECT ST_AsGeoJSON(geometry)::json AS geometry FROM geofences WHERE id = ?',
      [$this->id]
    );

    return $result?->geometry ? json_decode($result->geometry, true) : null;
  }
}
