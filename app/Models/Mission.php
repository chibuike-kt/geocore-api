<?php
// app/Models/Mission.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use TarfinLabs\LaravelSpatial\Traits\HasSpatial;

class Mission extends BaseModel
{
  use HasFactory;
  use HasSpatial;

  protected $fillable = [
    'name',
    'operator',
    'status',
    'start_time',
    'end_time',
    'planned_area',
    'metadata',
  ];

  protected $casts = [
    'metadata'   => 'array',
    'start_time' => 'datetime',
    'end_time'   => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  protected array $spatialFields = ['planned_area'];

  const STATUS_PLANNED   = 'planned';
  const STATUS_ACTIVE    = 'active';
  const STATUS_COMPLETED = 'completed';
  const STATUS_ABORTED   = 'aborted';

  const STATUSES = [
    self::STATUS_PLANNED,
    self::STATUS_ACTIVE,
    self::STATUS_COMPLETED,
    self::STATUS_ABORTED,
  ];

  /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

  public function telemetry(): HasMany
  {
    return $this->hasMany(Telemetry::class)->orderBy('recorded_at');
  }

  public function geofenceEvents(): HasMany
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
    return $query->where('status', self::STATUS_ACTIVE);
  }

  public function scopeByOperator($query, string $operator)
  {
    return $query->where('operator', $operator);
  }

  /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

  public function isActive(): bool
  {
    return $this->status === self::STATUS_ACTIVE;
  }

  public function duration(): ?int
  {
    if (!$this->start_time || !$this->end_time) {
      return null;
    }

    return $this->start_time->diffInSeconds($this->end_time);
  }
}
