<?php

// app/Http/Controllers/ExportController.php

namespace App\Http\Controllers;

use App\Http\Requests\Export\ExportRequest;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ExportController extends Controller
{
  public function __construct(
    protected ExportService $service
  ) {}

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/export
    |
    | Triggers an export. If async=true or dataset is large (>500 features),
    | the job is queued and an export_id is returned for polling.
    | Otherwise the export runs synchronously and returns a download URL.
    |--------------------------------------------------------------------------
    */

  public function export(ExportRequest $request): JsonResponse
  {
    $datasetId = $request->input('dataset_id');
    $format    = $request->input('format');
    $async     = (bool) $request->input('async', false);

    $filters = array_filter([
      'geometry_type' => $request->input('geometry_type'),
    ]);

    if ($async) {
      $result = $this->service->dispatch($datasetId, $format, $filters);
    } else {
      $result = $this->service->exportSync($datasetId, $format, $filters);
    }

    return response()->json($result, 202);
  }

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/export/status/{exportId}
    |
    | Poll this endpoint to check export progress.
    | Returns: pending | processing | complete | failed
    |--------------------------------------------------------------------------
    */

  public function status(string $exportId): JsonResponse
  {
    $status = $this->service->status($exportId);

    return response()->json($status);
  }

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/export/download/{exportId}
    |
    | Download the completed export file.
    | Returns the file directly as a downloadable response.
    |--------------------------------------------------------------------------
    */

  public function download(string $exportId): Response
  {
    $file = $this->service->download($exportId);

    return response($file['content'], 200, [
      'Content-Type'        => $file['mime_type'],
      'Content-Disposition' => 'attachment; filename="' . $file['filename'] . '"',
    ]);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/export/geojson  (convenience alias)
    |--------------------------------------------------------------------------
    */

  public function geojson(ExportRequest $request): JsonResponse
  {
    $result = $this->service->exportSync(
      datasetId: $request->input('dataset_id'),
      format: 'geojson',
      filters: array_filter([
        'geometry_type' => $request->input('geometry_type'),
      ]),
    );

    return response()->json($result, 202);
  }

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/export/csv  (convenience alias)
    |--------------------------------------------------------------------------
    */

  public function csv(ExportRequest $request): JsonResponse
  {
    $result = $this->service->exportSync(
      datasetId: $request->input('dataset_id'),
      format: 'csv',
      filters: array_filter([
        'geometry_type' => $request->input('geometry_type'),
      ]),
    );

    return response()->json($result, 202);
  }
}
