<?php

// app/Services/DatasetService.php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Dataset;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DatasetService
{
  public function __construct(
    protected DatasetRepositoryInterface $repository
  ) {}

  /*
    |--------------------------------------------------------------------------
    | List
    |--------------------------------------------------------------------------
    */

  public function list(int $perPage = 20): LengthAwarePaginator
  {
    return $this->repository->findAll($perPage);
  }

  /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

  public function findOrFail(string $id): Dataset
  {
    $dataset = $this->repository->findById($id);

    if (!$dataset) {
      throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
        "Dataset [{$id}] not found."
      );
    }

    return $dataset;
  }

  /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

  public function create(array $data): Dataset
  {
    // Ensure slug uniqueness
    $slug = Str::slug($data['name']);
    $data['slug'] = $this->uniqueSlug($slug);

    // Default SRID to platform default
    $data['srid'] = $data['srid'] ?? config('geocore.default_srid', 4326);

    $dataset = $this->repository->create($data);

    AuditLog::record(
      entityType: 'dataset',
      entityId: $dataset->id,
      action: 'created',
      payload: ['name' => $dataset->name, 'type' => $dataset->type],
    );

    return $dataset;
  }

  /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

  public function update(string $id, array $data): Dataset
  {
    // If name is being changed, regenerate slug
    if (isset($data['name'])) {
      $slug = Str::slug($data['name']);
      $data['slug'] = $this->uniqueSlug($slug, $id);
    }

    $dataset = $this->repository->update($id, $data);

    AuditLog::record(
      entityType: 'dataset',
      entityId: $dataset->id,
      action: 'updated',
      payload: $data,
    );

    return $dataset;
  }

  /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

  public function delete(string $id): void
  {
    $dataset = $this->findOrFail($id);

    $this->repository->delete($id);

    AuditLog::record(
      entityType: 'dataset',
      entityId: $id,
      action: 'deleted',
      payload: ['name' => $dataset->name],
    );
  }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

  /**
   * Generate a unique slug, optionally excluding a dataset ID (for updates).
   */
  private function uniqueSlug(string $base, ?string $excludeId = null): string
  {
    $slug      = $base;
    $counter   = 1;

    while (true) {
      $existing = $this->repository->findBySlug($slug);

      // No conflict found
      if (!$existing) {
        return $slug;
      }

      // Conflict is with the dataset we're updating — that's fine
      if ($excludeId && $existing->id === $excludeId) {
        return $slug;
      }

      // Conflict with another dataset — append counter
      $slug = $base . '-' . $counter++;
    }
  }
}
