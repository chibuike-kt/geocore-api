<?php

// app/Services/MissionService.php

namespace App\Services;

use App\Geo\GeometryHelper;
use App\Models\AuditLog;
use App\Models\Mission;
use App\Repositories\Contracts\MissionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class MissionService
{
  public function __construct(
    protected MissionRepositoryInterface $repository
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

  public function findOrFail(string $id): Mission
  {
    $mission = $this->repository->findById($id);

    if (!$mission) {
      throw new ModelNotFoundException("Mission [{$id}] not found.");
    }

    return $mission;
  }

  /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

  public function create(array $data): Mission
  {
    // Convert planned_area GeoJSON to WKT if provided
    if (!empty($data['planned_area'])) {
      GeometryHelper::validate($data['planned_area']);
      $data['planned_area_wkt'] = GeometryHelper::toWkt($data['planned_area']);
    }

    $mission = $this->repository->create($data);

    AuditLog::record(
      entityType: 'mission',
      entityId: $mission->id,
      action: 'created',
      payload: [
        'name'     => $mission->name,
        'operator' => $mission->operator,
        'status'   => $mission->status,
      ],
    );

    return $mission;
  }

  /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

  public function update(string $id, array $data): Mission
  {
    $this->findOrFail($id);

    $mission = $this->repository->update($id, $data);

    AuditLog::record(
      entityType: 'mission',
      entityId: $mission->id,
      action: 'updated',
      payload: $data,
    );

    return $mission;
  }

  /*
    |--------------------------------------------------------------------------
    | Status Transition
    |--------------------------------------------------------------------------
    */

  public function transition(string $id, string $status): Mission
  {
    $mission = $this->findOrFail($id);

    $this->assertValidTransition($mission->status, $status);

    $mission = $this->repository->updateStatus($id, $status);

    AuditLog::record(
      entityType: 'mission',
      entityId: $mission->id,
      action: 'status_changed',
      payload: [
        'from' => $mission->getOriginal('status'),
        'to'   => $status,
      ],
    );

    return $mission;
  }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

  /**
   * Enforce valid mission status transitions.
   *
   * planned   → active
   * active    → completed | aborted
   * completed → (terminal)
   * aborted   → (terminal)
   */
  private function assertValidTransition(string $from, string $to): void
  {
    $allowed = [
      Mission::STATUS_PLANNED   => [Mission::STATUS_ACTIVE],
      Mission::STATUS_ACTIVE    => [Mission::STATUS_COMPLETED, Mission::STATUS_ABORTED],
      Mission::STATUS_COMPLETED => [],
      Mission::STATUS_ABORTED   => [],
    ];

    if (!in_array($to, $allowed[$from] ?? [])) {
      throw new \InvalidArgumentException(
        "Cannot transition mission from [{$from}] to [{$to}]."
      );
    }
  }
}
