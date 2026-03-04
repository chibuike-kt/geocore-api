<?php

// app/Http/Resources/MissionResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class MissionResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id'           => $this->id,
      'name'         => $this->name,
      'operator'     => $this->operator,
      'status'       => $this->status,
      'start_time'   => $this->start_time?->toISOString(),
      'end_time'     => $this->end_time?->toISOString(),
      'duration_sec' => $this->duration(),
      'planned_area' => $this->resolvePlannedArea(),
      'metadata'     => $this->metadata ?? [],
      'created_at'   => $this->created_at?->toISOString(),
      'updated_at'   => $this->updated_at?->toISOString(),
    ];
  }

  private function resolvePlannedArea(): mixed
  {
    if (!$this->id) {
      return null;
    }

    $result = DB::selectOne(
      'SELECT ST_AsGeoJSON(planned_area)::json AS geometry FROM missions WHERE id = ?',
      [$this->id]
    );

    return $result?->geometry ? json_decode($result->geometry, true) : null;
  }
}
