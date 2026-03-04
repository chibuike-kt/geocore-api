<?php
// app/Models/Geofence.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use TarfinLabs\LaravelSpatial\Traits\HasSpatial;

class Geofence extends BaseModel
{
  use HasFactory;

  protected $fillable = [
    'name',
    'type',
    'geometry',
    'altitude_min',
    'altitude_max',
    'active',
    'metadata',
  ];

  protected $casts = [
    'metadata'    => 'array',
    'active'      => 'boolean',
    'altitude_min' => 'float',
    'altitude_max' => 'float',
    'created_at'  => 'datetime',
    'updated_at'  => 'datetime',
  ];

  protected array $spatialFields = ['geometry'];

  const TYPE_RESTRICTION = 'restriction';
  const TYPE_ADVISORY    = 'advisory';
  const TYPE_SURVEY      = 'survey';
  const TYPE_CUSTOM      = 'custom';

  /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

  public function events(): HasMany
  {
    return $this->hasMany(GeofenceEvent::class);
  }

  /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

  public function scopeActive($query)
  {
    return $query->where('active', true);
  }

  public function scopeOfType($query, string $type)
  {
    return $query->where('type', $type);
  }
}
