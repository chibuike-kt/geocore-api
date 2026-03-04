<?php

// app/Services/GeofenceEvaluator.php

namespace App\Services;

use App\Events\GeofenceEntered;
use App\Events\GeofenceExited;
use App\Models\GeofenceEvent;
use App\Repositories\Contracts\GeofenceRepositoryInterface;
use Illuminate\Support\Collection;

class GeofenceEvaluator
{
  public function __construct(
    protected GeofenceRepositoryInterface $repository
  ) {}

  /*
    |--------------------------------------------------------------------------
    | Evaluate a position against all active geofences
    |--------------------------------------------------------------------------
    |
    | This is the core algorithm:
    |
    | 1. Find all active geofences that currently contain the point
    | 2. For each geofence containing the point:
    |    - If last event was not 'enter' → fire ENTER event
    | 3. For all active geofences NOT containing the point:
    |    - If last event was 'enter' → fire EXIT event
    |
    | This handles the stateful transition correctly regardless of
    | how frequently positions are evaluated.
    |
    */

  public function evaluate(
    float   $lat,
    float   $lng,
    float   $altitude = null,
    ?string $missionId = null,
    ?string $recordedAt = null,
  ): array {
    $recordedAt    = $recordedAt ?? now()->toISOString();
    $events        = [];

    // Step 1 — Get all active geofences currently containing this point
    $containingIds = $this->repository
      ->findContainingPoint($lat, $lng)
      ->pluck('id')
      ->toArray();

    // Step 2 — Get all active geofences
    $allActive = $this->repository->findActive();

    foreach ($allActive as $geofence) {
      $isInside  = in_array($geofence->id, $containingIds);
      $lastEvent = $this->repository->getLastEvent($geofence->id, $missionId);

      // Altitude check — if geofence has altitude bounds, check them
      if ($isInside && $altitude !== null) {
        $isInside = $this->checkAltitude(
          $altitude,
          $geofence->altitude_min,
          $geofence->altitude_max
        );
      }

      $transition = $this->detectTransition($isInside, $lastEvent);

      if ($transition === null) {
        continue; // No state change
      }

      // Record the event
      $event = $this->repository->recordEvent([
        'geofence_id' => $geofence->id,
        'mission_id'  => $missionId,
        'event_type'  => $transition,
        'lat'         => $lat,
        'lng'         => $lng,
        'recorded_at' => $recordedAt,
      ]);

      // Fire Laravel events for listeners (webhook, notification etc.)
      if ($transition === GeofenceEvent::EVENT_ENTER) {
        event(new GeofenceEntered($geofence, $event, $missionId));
      } else {
        event(new GeofenceExited($geofence, $event, $missionId));
      }

      $events[] = [
        'geofence_id'   => $geofence->id,
        'geofence_name' => $geofence->name,
        'geofence_type' => $geofence->type,
        'event_type'    => $transition,
        'recorded_at'   => $recordedAt,
      ];
    }

    return [
      'lat'        => $lat,
      'lng'        => $lng,
      'altitude'   => $altitude,
      'mission_id' => $missionId,
      'evaluated_at'      => $recordedAt,
      'geofences_checked' => $allActive->count(),
      'events_fired'      => count($events),
      'events'            => $events,
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Detect state transition
    |--------------------------------------------------------------------------
    |
    | Returns 'enter', 'exit', or null (no change needed).
    |
    | State table:
    | isInside=true,  lastEvent=null   → ENTER  (first time inside)
    | isInside=true,  lastEvent=enter  → null   (already inside)
    | isInside=true,  lastEvent=exit   → ENTER  (re-entered)
    | isInside=false, lastEvent=null   → null   (never entered)
    | isInside=false, lastEvent=enter  → EXIT   (just left)
    | isInside=false, lastEvent=exit   → null   (already outside)
    |
    */

  private function detectTransition(bool $isInside, ?GeofenceEvent $lastEvent): ?string
  {
    $lastType = $lastEvent?->event_type;

    if ($isInside && ($lastType === null || $lastType === GeofenceEvent::EVENT_EXIT)) {
      return GeofenceEvent::EVENT_ENTER;
    }

    if (!$isInside && $lastType === GeofenceEvent::EVENT_ENTER) {
      return GeofenceEvent::EVENT_EXIT;
    }

    return null;
  }

  /*
    |--------------------------------------------------------------------------
    | Altitude bounds check
    |--------------------------------------------------------------------------
    */

  private function checkAltitude(
    float  $altitude,
    ?float $min,
    ?float $max
  ): bool {
    if ($min !== null && $altitude < $min) {
      return false;
    }

    if ($max !== null && $altitude > $max) {
      return false;
    }

    return true;
  }
}
