<?php
// app/Models/Dataset.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Dataset extends BaseModel
{
  use HasFactory;

  protected $fillable = [
    'name',
    'slug',
    'description',
    'type',
    'srid',
    'metadata',
  ];

  protected $casts = [
    'metadata'   => 'array',
    'srid'       => 'integer',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  /**
   * Valid dataset types.
   */
  const TYPES = [
    'drone_mission',
    'assets',
    'infrastructure',
    'survey_zone',
    'locations',
    'custom',
  ];

  /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

  public function features(): HasMany
  {
    return $this->hasMany(Feature::class);
  }

  /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

  public function scopeOfType($query, string $type)
  {
    return $query->where('type', $type);
  }

    /*
    |--------------------------------------------------------------------------
    | Mutators
    |--------------------------------------------------------------------------
    */

  /**
   * Auto-generate slug from name if not provided.
   */
  protected static function booted(): void
  {
    static::creating(function (Dataset $dataset) {
      if (empty($dataset->slug)) {
        $dataset->slug = Str::slug($dataset->name) . '-' . Str::random(6);
      }
    });
  }
}
