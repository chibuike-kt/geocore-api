<?php

// app/Jobs/ExportDatasetJob.php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportDatasetJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries   = 3;
  public int $timeout = 300; // 5 minutes max

  public function __construct(
    public readonly string $exportId,
    public readonly string $datasetId,
    public readonly string $format,     // geojson | csv
    public readonly array  $filters,
  ) {}

  public function handle(): void
  {
    // Mark job as processing
    $this->updateStatus('processing');

    try {
      $path = match ($this->format) {
        'geojson' => $this->exportGeoJson(),
        'csv'     => $this->exportCsv(),
        default   => throw new \InvalidArgumentException(
          "Unsupported format [{$this->format}]"
        ),
      };

      // Mark complete with file path
      $this->updateStatus('complete', $path);

      AuditLog::record(
        entityType: 'dataset',
        entityId: $this->datasetId,
        action: 'exported',
        payload: [
          'export_id' => $this->exportId,
          'format'    => $this->format,
          'path'      => $path,
        ],
      );
    } catch (\Throwable $e) {
      $this->updateStatus('failed', null, $e->getMessage());
      throw $e;
    }
  }

  /*
    |--------------------------------------------------------------------------
    | GeoJSON Export
    |--------------------------------------------------------------------------
    */

  private function exportGeoJson(): string
  {
    $features = $this->fetchFeatures();

    $collection = [
      'type'     => 'FeatureCollection',
      'features' => [],
    ];

    foreach ($features as $row) {
      $collection['features'][] = [
        'type'       => 'Feature',
        'id'         => $row->id,
        'geometry'   => json_decode($row->geometry, true),
        'properties' => array_merge(
          is_string($row->properties)
            ? json_decode($row->properties, true) ?? []
            : (array) $row->properties,
          [
            'name'          => $row->name,
            'geometry_type' => $row->geometry_type,
            'source_id'     => $row->source_id,
            'created_at'    => $row->created_at,
          ]
        ),
      ];
    }

    $content  = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $filename = $this->buildFilename('geojson');

    Storage::disk(config('geocore.export.disk'))
      ->put($this->exportPath($filename), $content);

    return $this->exportPath($filename);
  }

  /*
    |--------------------------------------------------------------------------
    | CSV Export
    |--------------------------------------------------------------------------
    */

  private function exportCsv(): string
  {
    $features = $this->fetchFeatures();
    $filename = $this->buildFilename('csv');
    $path     = $this->exportPath($filename);

    $disk   = Storage::disk(config('geocore.export.disk'));
    $handle = tmpfile();

    // Header row
    fputcsv($handle, [
      'id',
      'name',
      'geometry_type',
      'longitude',
      'latitude',
      'source_id',
      'properties',
      'created_at',
    ]);

    foreach ($features as $row) {
      $geometry = json_decode($row->geometry, true);

      // Extract centroid coordinates for CSV representation
      [$lng, $lat] = $this->extractCentroid($geometry);

      fputcsv($handle, [
        $row->id,
        $row->name,
        $row->geometry_type,
        $lng,
        $lat,
        $row->source_id,
        is_string($row->properties) ? $row->properties : json_encode($row->properties),
        $row->created_at,
      ]);
    }

    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    $disk->put($path, $content);

    return $path;
  }

  /*
    |--------------------------------------------------------------------------
    | Shared Fetch
    |--------------------------------------------------------------------------
    */

  private function fetchFeatures(): array
  {
    $query  = "
            SELECT
                id,
                name,
                geometry_type,
                source_id,
                properties,
                created_at,
                ST_AsGeoJSON(geometry) AS geometry
            FROM features
            WHERE dataset_id = ?
        ";

    $params = [$this->datasetId];

    // Apply optional geometry type filter
    if (!empty($this->filters['geometry_type'])) {
      $query    .= ' AND geometry_type = ?';
      $params[]  = $this->filters['geometry_type'];
    }

    $query .= ' ORDER BY created_at ASC';

    return DB::select($query, $params);
  }

  /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

  private function extractCentroid(array $geometry): array
  {
    return match ($geometry['type']) {
      'Point'      => $geometry['coordinates'],
      'LineString' => $geometry['coordinates'][0],
      'Polygon'    => $geometry['coordinates'][0][0],
      default      => [null, null],
    };
  }

  private function buildFilename(string $ext): string
  {
    return "dataset-{$this->datasetId}-{$this->exportId}.{$ext}";
  }

  private function exportPath(string $filename): string
  {
    return config('geocore.export.path', 'exports') . '/' . $filename;
  }

  private function updateStatus(
    string  $status,
    ?string $path  = null,
    ?string $error = null
  ): void {
    $ttl = config('geocore.export.ttl', 3600);

    Cache::put("export:{$this->exportId}", [
      'export_id'  => $this->exportId,
      'dataset_id' => $this->datasetId,
      'format'     => $this->format,
      'status'     => $status,
      'path'       => $path,
      'error'      => $error,
      'updated_at' => now()->toISOString(),
    ], $ttl);
  }

  public function failed(\Throwable $e): void
  {
    $this->updateStatus('failed', null, $e->getMessage());
  }
}
