<?php
// app/Repositories/Contracts/GeofenceRepositoryInterface.php

namespace App\Repositories\Contracts;

use App\Models\Geofence;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface GeofenceRepositoryInterface
{
  public function findAll(int $perPage = 20): LengthAwarePaginator;
  public function findById(string $id): ?Geofence;
  public function findActive(): Collection;
  public function create(array $data): Geofence;
  public function update(string $id, array $data): Geofence;
  public function findContainingPoint(float $lat, float $lng): Collection;
}
