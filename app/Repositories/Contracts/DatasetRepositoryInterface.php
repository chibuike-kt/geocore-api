<?php
// app/Repositories/Contracts/DatasetRepositoryInterface.php

namespace App\Repositories\Contracts;

use App\Models\Dataset;
use Illuminate\Pagination\LengthAwarePaginator;

interface DatasetRepositoryInterface
{
  public function findAll(int $perPage = 20): LengthAwarePaginator;
  public function findById(string $id): ?Dataset;
  public function findBySlug(string $slug): ?Dataset;
  public function create(array $data): Dataset;
  public function update(string $id, array $data): Dataset;
  public function delete(string $id): bool;
}
