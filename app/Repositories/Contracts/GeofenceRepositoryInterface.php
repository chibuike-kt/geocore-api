<?php

namespace App\Repositories\Contracts;

use App\Models\Geofence;
use App\Models\GeofenceEvent;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface GeofenceRepositoryInterface
{
  public function findAll(int $perPage = 20): LengthAwarePaginator;
  public function findById(string $id): ?Geofence;
  public function findActive(): Collection;
  public function findContainingPoint(float $lat, float $lng): Collection;
  public function findIntersectingPoint(float $lat, float $lng): Collection;
  public function create(array $data): Geofence;
  public function update(string $id, array $data): Geofence;
  public function recordEvent(array $data): GeofenceEvent;
  public function getLastEvent(string $geofenceId, ?string $missionId): ?GeofenceEvent;
  public function getEvents(string $geofenceId): Collection;
}
