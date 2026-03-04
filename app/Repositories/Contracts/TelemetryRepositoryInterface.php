<?php

namespace App\Repositories\Contracts;

use App\Models\Telemetry;
use Illuminate\Support\Collection;

interface TelemetryRepositoryInterface
{
  public function create(array $data): Telemetry;
  public function bulkCreate(array $records): int;
  public function getTrack(string $missionId): Collection;
  public function getLatestPing(string $missionId): ?Telemetry;
  public function getTrackAsLineString(string $missionId): ?object;
}
