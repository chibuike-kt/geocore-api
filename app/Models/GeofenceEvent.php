<?php
// app/Models/GeofenceEvent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use TarfinLabs\LaravelSpatial\Traits\HasSpatial;
use Illuminate\Database\Eloquent\Model;

class GeofenceEvent extends Model
{
  use HasFactory;
  use HasSpatial;

  public $incrementing = true;
  protected $keyType   = 'integer';

  protected $fillable = [
    'geofence_id',
    'mission_id',
    'event_type',
    'position',
    'recorded_at',
  ];

  protected $casts = [
    'recorded_at' => 'datetime',
    'created_at'  => 'datetime',
  ];

  protected array $spatialFields = ['position'];

  const UPDATED_AT = null;

  const EVENT_ENTER = 'enter';
  const EVENT_EXIT  = 'exit';

  /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

  public function geofence(): BelongsTo
  {
    return $this->belongsTo(Geofence::class);
  }

  public function mission(): BelongsTo
  {
    return $this->belongsTo(Mission::class);
  }
}
