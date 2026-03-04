<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatasetResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id'           => $this->id,
      'name'         => $this->name,
      'slug'         => $this->slug,
      'type'         => $this->type,
      'description'  => $this->description,
      'srid'         => $this->srid,
      'metadata'     => $this->metadata ?? [],
      'feature_count' => $this->whenCounted('features'),
      'created_at'   => $this->created_at?->toISOString(),
      'updated_at'   => $this->updated_at?->toISOString(),
    ];
  }
}
