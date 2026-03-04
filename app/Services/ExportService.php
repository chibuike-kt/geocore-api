<?php

// app/Services/ExportService.php

namespace App\Services;

use App\Jobs\ExportDatasetJob;
use App\Models\AuditLog;
use App\Repositories\Contracts\DatasetRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportService
{
  public function __construct(
    protected DatasetRepositoryInterface $datasetRepository,
  ) {}

  /*
    |--------------------------------------------------------------------------
    | Dispatch Export Job
    |--------------------------------------------------------------------------
    |
    | Creates an export ID, stores initial status in cache,
    | dispatches the queued job, and returns immediately.
    | The client polls /export/status/{exportId} to check progress.
    |
    */

  public function dispatch(
    string $datasetId,
    string $format,
    array  $filters = []
  ): array {
    $this->assertDatasetExists($datasetId);

    $exportId = (string) Str::uuid();

    // Store pending status immediately so client can start polling
    Cache::put("export:{$exportId}", [
      'export_id'  => $exportId,
      'dataset_id' => $datasetId,
      'format'     => $format,
      'status'     => 'pending',
      'path'       => null,
      'error'      => null,
      'updated_at' => now()->toISOString(),
    ], config('geocore.export.ttl', 3600));

    // Dispatch the queued job
    ExportDatasetJob::dispatch($exportId, $datasetId, $format, $filters);

    AuditLog::record(
      entityType: 'dataset',
      entityId: $datasetId,
      action: 'export_dispatched',
      payload: [
        'export_id' => $exportId,
        'format'    => $format,
      ],
    );

    return [
      'export_id'  => $exportId,
      'dataset_id' => $datasetId,
      'format'     => $format,
      'status'     => 'pending',
      'message'    => 'Export queued. Poll /api/v1/export/status/' . $exportId . ' to check progress.',
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Synchronous Export (small datasets)
    |--------------------------------------------------------------------------
    |
    | For small datasets (under 500 features), export synchronously
    | and return a download URL immediately.
    |
    */

  public function exportSync(
    string $datasetId,
    string $format,
    array  $filters = []
  ): array {
    $this->assertDatasetExists($datasetId);

    $count = DB::table('features')
      ->where('dataset_id', $datasetId)
      ->count();

    // Force async for large datasets
    if ($count > 500) {
      return $this->dispatch($datasetId, $format, $filters);
    }

    $exportId = (string) Str::uuid();

    // Run synchronously
    $job = new ExportDatasetJob($exportId, $datasetId, $format, $filters);
    $job->handle();

    $status = Cache::get("export:{$exportId}");

    return array_merge($status, [
      'download_url' => $status['status'] === 'complete'
        ? $this->buildDownloadUrl($exportId)
        : null,
    ]);
  }

  /*
    |--------------------------------------------------------------------------
    | Status Check
    |--------------------------------------------------------------------------
    */

  public function status(string $exportId): array
  {
    $status = Cache::get("export:{$exportId}");

    if (!$status) {
      throw new ModelNotFoundException(
        "Export [{$exportId}] not found or has expired."
      );
    }

    if ($status['status'] === 'complete') {
      $status['download_url'] = $this->buildDownloadUrl($exportId);
    }

    return $status;
  }

  /*
    |--------------------------------------------------------------------------
    | Download
    |--------------------------------------------------------------------------
    */

  public function download(string $exportId): array
  {
    $status = $this->status($exportId);

    if ($status['status'] !== 'complete') {
      throw new \InvalidArgumentException(
        "Export [{$exportId}] is not ready. Status: [{$status['status']}]."
      );
    }

    $disk    = Storage::disk(config('geocore.export.disk'));
    $path    = $status['path'];

    if (!$disk->exists($path)) {
      throw new \RuntimeException(
        "Export file not found on disk. It may have expired."
      );
    }

    return [
      'content'   => $disk->get($path),
      'filename'  => basename($path),
      'mime_type' => $status['format'] === 'geojson'
        ? 'application/geo+json'
        : 'text/csv',
    ];
  }

  /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

  private function buildDownloadUrl(string $exportId): string
  {
    return url("/api/v1/export/download/{$exportId}");
  }

  private function assertDatasetExists(string $datasetId): void
  {
    if (!$this->datasetRepository->findById($datasetId)) {
      throw new ModelNotFoundException(
        "Dataset [{$datasetId}] not found."
      );
    }
  }
}
