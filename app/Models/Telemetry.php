<?php
// app/Models/Telemetry.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use TarfinLabs\LaravelSpatial\Traits\HasSpatial;

class Telemetry extends BaseModel
{
  use HasFactory;
  use HasSpatial;

  /**
   * Telemetry uses BIGSERIAL — override UUID behaviour.
   */
  public $incrementing = true;
  protected $keyType   = 'integer';

  protected $fillable = [
    'mission_id',
    'recorded_at',
    'position',
    'altitude',
    'speed',
    'heading',
    'metadata',
  ];

  protected $casts = [
    'metadata'    => 'array',
    'altitude'    => 'float',
    'speed'       => 'float',
    'heading'     => 'float',
    'recorded_at' => 'datetime',
    'created_at'  => 'datetime',
  ];

  protected array $spatialFields = ['position'];

  /**
   * No updated_at column on telemetry — insert only.
   */
  const UPDATED_AT = null;

  /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

  public function mission(): BelongsTo
  {
    return $this->belongsTo(Mission::class);
  }

  /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

  public function scopeForMission($query, string $missionId)
  {
    return $query->where('mission_id', $missionId);
  }

  public function scopeInTimeRange($query, $from, $to)
  {
    return $query->whereBetween('recorded_at', [$from, $to]);
  }
}
