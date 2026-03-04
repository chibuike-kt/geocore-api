<?php

// app/Events/GeofenceEntered.php

namespace App\Events;

use App\Models\Geofence;
use App\Models\GeofenceEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GeofenceEntered
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public function __construct(
    public readonly Geofence      $geofence,
    public readonly GeofenceEvent $event,
    public readonly ?string       $missionId,
  ) {}
}
