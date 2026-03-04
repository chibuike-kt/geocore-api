<?php

// app/Services/TelemetryService.php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Mission;
use App\Models\Telemetry;
use App\Repositories\Contracts\MissionRepositoryInterface;
use App\Repositories\Contracts\TelemetryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class TelemetryService
{
  public function __construct(
    protected TelemetryRepositoryInterface $telemetryRepository,
    protected MissionRepositoryInterface   $missionRepository,
  ) {}

  /*
    |--------------------------------------------------------------------------
    | Single Ping Ingestion
    |--------------------------------------------------------------------------
    */

  public function ingest(string $missionId, array $data): Telemetry
  {
    $mission = $this->assertMissionActive($missionId);

    $telemetry = $this->telemetryRepository->create([
      'mission_id'  => $missionId,
      'recorded_at' => $data['recorded_at'],
      'lat'         => $data['lat'],
      'lng'         => $data['lng'],
      'altitude'    => $data['altitude'] ?? null,
      'speed'       => $data['speed']    ?? null,
      'heading'     => $data['heading']  ?? null,
      'metadata'    => $data['metadata'] ?? [],
    ]);

    return $telemetry;
  }

  /*
    |--------------------------------------------------------------------------
    | Batch Ping Ingestion
    |--------------------------------------------------------------------------
    */

  public function ingestBatch(string $missionId, array $pings): array
  {
    $this->assertMissionActive($missionId);

    $maxBatch = config('geocore.telemetry.batch_size', 500);

    if (count($pings) > $maxBatch) {
      throw new \InvalidArgumentException(
        "Batch size exceeds maximum of {$maxBatch} pings per request."
      );
    }

    $records = array_map(fn($ping) => array_merge($ping, [
      'mission_id' => $missionId,
    ]), $pings);

    $inserted = $this->telemetryRepository->bulkCreate($records);

    AuditLog::record(
      entityType: 'mission',
      entityId: $missionId,
      action: 'telemetry_batch_ingested',
      payload: ['count' => $inserted],
    );

    return [
      'mission_id' => $missionId,
      'inserted'   => $inserted,
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Track — full flight path
    |--------------------------------------------------------------------------
    */

  public function getTrack(string $missionId): array
  {
    $mission = $this->missionRepository->findById($missionId);

    if (!$mission) {
      throw new ModelNotFoundException("Mission [{$missionId}] not found.");
    }

    $summary  = $this->telemetryRepository->getTrackAsLineString($missionId);
    $pings    = $this->telemetryRepository->getTrack($missionId);

    // Not enough points for a LineString
    if (!$summary || (int) $summary->point_count < 2) {
      return [
        'mission_id'  => $missionId,
        'point_count' => (int) ($summary->point_count ?? 0),
        'track'       => null,
        'pings'       => $this->formatPings($pings),
        'stats'       => null,
      ];
    }

    return [
      'mission_id'  => $missionId,
      'point_count' => (int) $summary->point_count,
      'track'       => [
        'type'     => 'Feature',
        'geometry' => $summary->linestring,
        'properties' => [
          'mission_id'   => $missionId,
          'started_at'   => $summary->started_at,
          'ended_at'     => $summary->ended_at,
        ],
      ],
      'pings'       => $this->formatPings($pings),
      'stats'       => [
        'started_at'   => $summary->started_at,
        'ended_at'     => $summary->ended_at,
        'min_altitude' => $summary->min_altitude ? round((float) $summary->min_altitude, 2) : null,
        'max_altitude' => $summary->max_altitude ? round((float) $summary->max_altitude, 2) : null,
        'avg_speed'    => $summary->avg_speed    ? round((float) $summary->avg_speed, 2)    : null,
      ],
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

  private function assertMissionActive(string $missionId): Mission
  {
    $mission = $this->missionRepository->findById($missionId);

    if (!$mission) {
      throw new ModelNotFoundException("Mission [{$missionId}] not found.");
    }

    if ($mission->status !== Mission::STATUS_ACTIVE) {
      throw new \InvalidArgumentException(
        "Telemetry can only be ingested for active missions. " .
          "Mission [{$missionId}] is [{$mission->status}]."
      );
    }

    return $mission;
  }

  private function formatPings(Collection $pings): array
  {
    return $pings->map(fn($ping) => [
      'id'          => $ping->id,
      'recorded_at' => $ping->recorded_at,
      'position'    => $ping->position,
      'altitude'    => $ping->altitude    ? round((float) $ping->altitude, 2) : null,
      'speed'       => $ping->speed       ? round((float) $ping->speed, 4)    : null,
      'heading'     => $ping->heading     ? round((float) $ping->heading, 2)  : null,
    ])->values()->all();
  }
}
