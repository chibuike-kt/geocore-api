<?php

// tests/Feature/FeatureTest.php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FeatureTest extends TestCase
{
  use RefreshDatabase;

  /*
    |--------------------------------------------------------------------------
    | Single Insert
    |--------------------------------------------------------------------------
    */

  public function test_can_insert_a_point_feature(): void
  {
    $dataset  = $this->createDataset();
    $response = $this->postJson("/api/v1/datasets/{$dataset['id']}/features", [
      'name'       => 'Lagos Tower',
      'source_id'  => 'tower-001',
      'geometry'   => $this->pointGeometry(3.3792, 6.5244),
      'properties' => ['height' => 45],
    ]);

    $response->assertStatus(201)
      ->assertJsonPath('data.name', 'Lagos Tower')
      ->assertJsonPath('data.geometry_type', 'Point')
      ->assertJsonStructure(['data' => ['id', 'geometry', 'properties']]);

    $this->assertDatabaseHas('features', [
      'name'          => 'Lagos Tower',
      'geometry_type' => 'Point',
      'source_id'     => 'tower-001',
    ]);
  }

  public function test_can_insert_a_polygon_feature(): void
  {
    $dataset  = $this->createDataset();
    $response = $this->postJson("/api/v1/datasets/{$dataset['id']}/features", [
      'name'     => 'Restricted Zone Alpha',
      'geometry' => $this->polygonGeometry(),
    ]);

    $response->assertStatus(201)
      ->assertJsonPath('data.geometry_type', 'Polygon');
  }

  public function test_can_insert_a_linestring_feature(): void
  {
    $dataset  = $this->createDataset();
    $response = $this->postJson("/api/v1/datasets/{$dataset['id']}/features", [
      'name'     => 'Flight Path',
      'geometry' => $this->lineStringGeometry(),
    ]);

    $response->assertStatus(201)
      ->assertJsonPath('data.geometry_type', 'LineString');
  }

  /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    */

  public function test_duplicate_geometry_is_skipped(): void
  {
    $dataset  = $this->createDataset();
    $geometry = $this->pointGeometry(3.3792, 6.5244);

    // Insert once
    $this->postJson("/api/v1/datasets/{$dataset['id']}/features", [
      'geometry' => $geometry,
    ])->assertStatus(201);

    // Insert same geometry again — should not duplicate
    $this->postJson("/api/v1/datasets/{$dataset['id']}/features", [
      'geometry' => $geometry,
    ])->assertStatus(201);

    // Only one row should exist
    $this->assertDatabaseCount('features', 1);
  }

  /*
    |--------------------------------------------------------------------------
    | Bulk Ingestion
    |--------------------------------------------------------------------------
    */

  public function test_can_bulk_insert_geojson_feature_collection(): void
  {
    $dataset    = $this->createDataset();
    $collection = $this->featureCollection(5);

    $response = $this->postJson(
      "/api/v1/datasets/{$dataset['id']}/features/bulk",
      $collection
    );

    $response->assertStatus(201)
      ->assertJsonPath('inserted', 5)
      ->assertJsonPath('skipped',  0)
      ->assertJsonPath('submitted', 5);

    $this->assertDatabaseCount('features', 5);
  }

  public function test_bulk_insert_skips_duplicates(): void
  {
    $dataset    = $this->createDataset();
    $collection = $this->featureCollection(3);

    // First bulk insert
    $this->postJson(
      "/api/v1/datasets/{$dataset['id']}/features/bulk",
      $collection
    )->assertStatus(201);

    // Second bulk insert with same data
    $response = $this->postJson(
      "/api/v1/datasets/{$dataset['id']}/features/bulk",
      $collection
    );

    $response->assertStatus(201)
      ->assertJsonPath('inserted', 0)
      ->assertJsonPath('skipped',  3);

    // Still only 3 rows
    $this->assertDatabaseCount('features', 3);
  }

  public function test_bulk_insert_validates_feature_collection_type(): void
  {
    $dataset  = $this->createDataset();
    $response = $this->postJson(
      "/api/v1/datasets/{$dataset['id']}/features/bulk",
      ['type' => 'Feature', 'features' => []]
    );

    $response->assertStatus(422);
  }

  public function test_bulk_insert_rejects_invalid_geometry(): void
  {
    $dataset    = $this->createDataset();
    $collection = [
      'type'     => 'FeatureCollection',
      'features' => [
        [
          'type'       => 'Feature',
          'geometry'   => [
            'type'        => 'Point',
            'coordinates' => [999, 999], // invalid coordinates
          ],
          'properties' => [],
        ],
      ],
    ];

    $response = $this->postJson(
      "/api/v1/datasets/{$dataset['id']}/features/bulk",
      $collection
    );

    // Bulk accepts partial success — invalid features go to errors array
    $response->assertStatus(201)
      ->assertJsonPath('inserted', 0);

    $this->assertNotEmpty($response->json('errors'));
  }

  /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

  public function test_feature_insert_validates_geometry_presence(): void
  {
    $dataset  = $this->createDataset();
    $response = $this->postJson(
      "/api/v1/datasets/{$dataset['id']}/features",
      ['name' => 'No geometry']
    );

    $response->assertStatus(422)
      ->assertJsonStructure(['errors' => ['geometry']]);
  }

  public function test_feature_insert_validates_polygon_closure(): void
  {
    $dataset  = $this->createDataset();
    $response = $this->postJson(
      "/api/v1/datasets/{$dataset['id']}/features",
      [
        'geometry' => [
          'type'        => 'Polygon',
          'coordinates' => [[
            [3.360, 6.510],
            [3.400, 6.510],
            [3.400, 6.545],
            // Missing closing coordinate — not closed
          ]],
        ],
      ]
    );

    $response->assertStatus(422);
  }

  /*
    |--------------------------------------------------------------------------
    | List
    |--------------------------------------------------------------------------
    */

  public function test_can_list_features_in_dataset(): void
  {
    $dataset = $this->createDataset();

    $this->createFeature($dataset['id'], ['name' => 'Feature A']);
    $this->createFeature($dataset['id'], [
      'name'     => 'Feature B',
      'geometry' => $this->pointGeometry(3.385, 6.530),
    ]);

    $response = $this->getJson("/api/v1/datasets/{$dataset['id']}/features");

    $response->assertStatus(200)
      ->assertJsonCount(2, 'data');
  }
}
