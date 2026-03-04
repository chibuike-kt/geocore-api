<?php
// app/Repositories/Contracts/MissionRepositoryInterface.php

namespace App\Repositories\Contracts;

use App\Models\Mission;
use Illuminate\Pagination\LengthAwarePaginator;

interface MissionRepositoryInterface
{
  public function findAll(int $perPage = 20): LengthAwarePaginator;
  public function findById(string $id): ?Mission;
  public function create(array $data): Mission;
  public function update(string $id, array $data): Mission;
  public function updateStatus(string $id, string $status): Mission;
}
