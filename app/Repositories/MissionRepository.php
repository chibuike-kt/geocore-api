<?php
namespace App\Repositories;

use App\Models\Mission;
use App\Repositories\Contracts\MissionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MissionRepository implements MissionRepositoryInterface
{
  public function __construct(
    protected Mission $model
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

  public function findById(string $id): ?Mission
  {
    return $this->model->find($id);
  }

  /*
    |--------------------------------------------------------------------------
    | Write Operations
    |--------------------------------------------------------------------------
    */

  public function create(array $data): Mission
  {
    return DB::transaction(function () use ($data) {
      $id = (string) \Illuminate\Support\Str::uuid();

      // planned_area is optional — only insert geometry if provided
      if (!empty($data['planned_area_wkt'])) {
        DB::statement("
                    INSERT INTO missions (
                        id, name, operator, status,
                        start_time, end_time,
                        planned_area, metadata,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?,
                        ST_GeomFromText(?, 4326), ?::jsonb,
                        NOW(), NOW()
                    )
                ", [
          $id,
          $data['name'],
          $data['operator'],
          $data['status'] ?? Mission::STATUS_PLANNED,
          $data['start_time'] ?? null,
          $data['end_time'] ?? null,
          $data['planned_area_wkt'],
          json_encode($data['metadata'] ?? []),
        ]);
      } else {
        DB::statement("
                    INSERT INTO missions (
                        id, name, operator, status,
                        start_time, end_time,
                        metadata, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?,
                        ?::jsonb, NOW(), NOW()
                    )
                ", [
          $id,
          $data['name'],
          $data['operator'],
          $data['status'] ?? Mission::STATUS_PLANNED,
          $data['start_time'] ?? null,
          $data['end_time'] ?? null,
          json_encode($data['metadata'] ?? []),
        ]);
      }

      return $this->model->findOrFail($id);
    });
  }

  public function update(string $id, array $data): Mission
  {
    return DB::transaction(function () use ($id, $data) {
      $mission = $this->model->findOrFail($id);
      $mission->update($data);
      return $mission->fresh();
    });
  }

  public function updateStatus(string $id, string $status): Mission
  {
    return DB::transaction(function () use ($id, $status) {
      $mission = $this->model->findOrFail($id);

      $updates = ['status' => $status];

      // Auto-set timestamps based on status transition
      if ($status === Mission::STATUS_ACTIVE && !$mission->start_time) {
        $updates['start_time'] = now();
      }

      if (in_array($status, [Mission::STATUS_COMPLETED, Mission::STATUS_ABORTED])) {
        $updates['end_time'] = now();
      }

      $mission->update($updates);
      return $mission->fresh();
    });
  }
}
