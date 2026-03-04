<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model; 

class AuditLog extends Model
{
  use HasFactory;

  public $incrementing = true;
  protected $keyType   = 'integer';

  const UPDATED_AT = null;

  protected $fillable = [
    'entity_type',
    'entity_id',
    'action',
    'payload',
    'ip_address',
    'user_agent',
  ];

  protected $casts = [
    'payload'    => 'array',
    'created_at' => 'datetime',
  ];

  public function scopeForEntity($query, string $type, string $id)
  {
    return $query->where('entity_type', $type)
      ->where('entity_id', $id);
  }

  public function scopeAction($query, string $action)
  {
    return $query->where('action', $action);
  }

  public static function record(
    string $entityType,
    string $entityId,
    string $action,
    array  $payload = [],
  ): self {
    return static::create([
      'entity_type' => $entityType,
      'entity_id'   => $entityId,
      'action'      => $action,
      'payload'     => $payload,
      'ip_address'  => request()->ip(),
      'user_agent'  => request()->userAgent(),
    ]);
  }
}
