<?php

// tests/TestCase.php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | PostGIS Setup
    |--------------------------------------------------------------------------
    | RefreshDatabase wraps tests in transactions and rolls back after each
    | test. PostGIS extensions persist across transactions so we only need
    | to ensure they exist once.
    */

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePostGisExtensions();
    }

    private function ensurePostGisExtensions(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
    }

    /*
    |--------------------------------------------------------------------------
    | Geometry Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * A simple GeoJSON Point geometry.
     */
    protected function pointGeometry(float $lng = 3.3792, float $lat = 6.5244): array
    {
        return [
            'type'        => 'Point',
            'coordinates' => [$lng, $lat],
        ];
    }

    /**
     * A simple closed GeoJSON Polygon geometry (Lagos area).
     */
    protected function polygonGeometry(): array
    {
        return [
            'type'        => 'Polygon',
            'coordinates' => [[
                [3.360, 6.510],
                [3.400, 6.510],
                [3.400, 6.545],
                [3.360, 6.545],
                [3.360, 6.510],
            ]],
        ];
    }

    /**
     * A GeoJSON LineString geometry.
     */
    protected function lineStringGeometry(): array
    {
        return [
            'type'        => 'LineString',
            'coordinates' => [
                [3.370, 6.520],
                [3.380, 6.525],
                [3.390, 6.530],
            ],
        ];
    }

    /**
     * A GeoJSON FeatureCollection with N point features.
     */
    protected function featureCollection(int $count = 3): array
    {
        $features = [];

        for ($i = 0; $i < $count; $i++) {
            $features[] = [
                'type'       => 'Feature',
                'id'         => "source-{$i}",
                'geometry'   => $this->pointGeometry(
                    3.370 + ($i * 0.005),
                    6.520 + ($i * 0.003)
                ),
                'properties' => ['name' => "Feature {$i}", 'index' => $i],
            ];
        }

        return [
            'type'     => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Factory Helpers
    |--------------------------------------------------------------------------
    | These insert directly via DB so tests aren't coupled to factory classes.
    */

    protected function createDataset(array $overrides = []): array
    {
        $data = array_merge([
            'name'        => 'Test Dataset',
            'type'        => 'locations',
            'description' => 'Test dataset for automated tests',
        ], $overrides);

        $response = $this->postJson('/api/v1/datasets', $data);
        $response->assertStatus(201);

        return $response->json('data');
    }

    protected function createFeature(string $datasetId, array $overrides = []): array
    {
        $data = array_merge([
            'name'       => 'Test Feature',
            'geometry'   => $this->pointGeometry(),
            'properties' => ['category' => 'test'],
        ], $overrides);

        $response = $this->postJson("/api/v1/datasets/{$datasetId}/features", $data);
        $response->assertStatus(201);

        return $response->json('data');
    }

    protected function createMission(array $overrides = []): array
    {
        $data = array_merge([
            'name'     => 'Test Mission',
            'operator' => 'Test Operator',
        ], $overrides);

        $response = $this->postJson('/api/v1/missions', $data);
        $response->assertStatus(201);

        return $response->json('data');
    }

    protected function createGeofence(array $overrides = []): array
    {
        $data = array_merge([
            'name'     => 'Test Geofence',
            'type'     => 'restriction',
            'geometry' => $this->polygonGeometry(),
        ], $overrides);

        $response = $this->postJson('/api/v1/geofences', $data);
        $response->assertStatus(201);

        return $response->json('data');
    }

    protected function activateMission(string $missionId): void
    {
        $this->patchJson("/api/v1/missions/{$missionId}/status", [
            'status' => 'active',
        ])->assertStatus(200);
    }
}
