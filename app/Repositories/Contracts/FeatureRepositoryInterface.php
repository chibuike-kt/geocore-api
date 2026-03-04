<?php
// app/Repositories/Contracts/FeatureRepositoryInterface.php

namespace App\Repositories\Contracts;

use App\Models\Feature;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface FeatureRepositoryInterface
{
  public function findByDataset(string $datasetId, int $perPage = 100): LengthAwarePaginator;
  public function findById(string $id): ?Feature;
  public function create(array $data): Feature;
  public function bulkCreate(string $datasetId, array $features): int;
  public function existsByHash(string $datasetId, string $hash): bool;
  public function existsBySourceId(string $datasetId, string $sourceId): bool;

  // Spatial queries
  public function findWithin(string $datasetId, string $polygonWkt): Collection;
  public function findWithinRadius(string $datasetId, float $lat, float $lng, float $radiusMeters): Collection;
  public function findNearest(string $datasetId, float $lat, float $lng, int $limit): Collection;
  public function findIntersecting(string $datasetId, string $geometryWkt): Collection;
}
