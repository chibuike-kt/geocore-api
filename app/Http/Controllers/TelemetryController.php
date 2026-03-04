<?php

// app/Http/Controllers/TelemetryController.php

namespace App\Http\Controllers;

use App\Http\Requests\Mission\IngestTelemetryRequest;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;

class TelemetryController extends Controller
{
  public function __construct(
    protected TelemetryService $service
  ) {}

  /*
    |--------------------------------------------------------------------------
    | POST /api/v1/missions/{mission}/telemetry
    |
    | Accepts either a single ping or a batch (pings array).
    |--------------------------------------------------------------------------
    */

  public function store(IngestTelemetryRequest $request, string $missionId): JsonResponse
  {
    // Batch ingestion
    if ($request->has('pings')) {
      $result = $this->service->ingestBatch($missionId, $request->input('pings'));

      return response()->json([
        'message'    => 'Telemetry batch ingested.',
        'mission_id' => $result['mission_id'],
        'inserted'   => $result['inserted'],
      ], 201);
    }

    // Single ping ingestion
    $telemetry = $this->service->ingest($missionId, $request->validated());

    return response()->json([
      'message'    => 'Telemetry ping recorded.',
      'mission_id' => $missionId,
      'id'         => $telemetry->id,
      'recorded_at' => $telemetry->recorded_at,
    ], 201);
  }

  /*
    |--------------------------------------------------------------------------
    | GET /api/v1/missions/{mission}/track
    |
    | Returns the full flight path as a GeoJSON LineString + ping list + stats.
    |--------------------------------------------------------------------------
    */

  public function track(string $missionId): JsonResponse
  {
    $track = $this->service->getTrack($missionId);

    return response()->json($track);
  }
}
