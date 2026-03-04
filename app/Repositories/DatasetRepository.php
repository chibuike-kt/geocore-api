<?php

// app/Repositories/DatasetRepository.php

namespace App\Repositories;

use App\Models\Dataset;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DatasetRepository implements DatasetRepositoryInterface
{
  public function __construct(
    protected Dataset $model
  ) {}

  /*
    |--------------------------------------------------------------------------
    | Read Operations
    |--------------------------------------------------------------------------
    */

  public function findAll(int $perPage = 20): LengthAwarePaginator
  {
    return $this->model
      ->orderBy('created_at', 'desc')
      ->paginate($perPage);
  }

  public function findById(string $id): ?Dataset
  {
    return $this->model->find($id);
  }

  public function findBySlug(string $slug): ?Dataset
  {
    return $this->model->where('slug', $slug)->first();
  }

  /*
    |--------------------------------------------------------------------------
    | Write Operations
    |--------------------------------------------------------------------------
    */

  public function create(array $data): Dataset
  {
    return DB::transaction(function () use ($data) {
      return $this->model->create($data);
    });
  }

  public function update(string $id, array $data): Dataset
  {
    return DB::transaction(function () use ($id, $data) {
      $dataset = $this->model->findOrFail($id);
      $dataset->update($data);
      return $dataset->fresh();
    });
  }

  public function delete(string $id): bool
  {
    return DB::transaction(function () use ($id) {
      $dataset = $this->model->findOrFail($id);
      return $dataset->delete();
    });
  }
}
