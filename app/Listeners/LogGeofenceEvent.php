<?php

// app/Listeners/LogGeofenceEvent.php

namespace App\Listeners;

use App\Models\AuditLog;

class LogGeofenceEvent
{
  /**
   * Handle both GeofenceEntered and GeofenceExited events.
   * Both events share the same shape so one listener handles both.
   */
  public function handle(object $event): void
  {
    AuditLog::record(
      entityType: 'geofence',
      entityId: $event->geofence->id,
      action: $event->event->event_type === 'enter'
        ? 'geofence_entered'
        : 'geofence_exited',
      payload: [
        'geofence_name' => $event->geofence->name,
        'geofence_type' => $event->geofence->type,
        'mission_id'    => $event->missionId,
        'recorded_at'   => $event->event->recorded_at,
      ],
    );
  }
}
