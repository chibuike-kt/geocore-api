<?php
// app/Models/BaseModel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

abstract class BaseModel extends Model
{
  use HasUuids;

  /**
   * Disable auto-incrementing — we use UUIDs.
   */
  public $incrementing = false;

  /**
   * Primary key type is string (UUID).
   */
  protected $keyType = 'string';
}
