<?php

// tests/Feature/SpatialQueryTest.php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SpatialQueryTest extends TestCase
{
  use RefreshDatabase;

  private array $dataset;

  protected function setUp(): void
  {
    parent::setUp();

    // Create a dataset with known features for all spatial tests
    $this->dataset = $this->createDataset(['name' => 'Spatial Test Dataset']);

    // Feature INSIDE the test polygon (3.360-3.400 lng, 6.510-6.545 lat)
    $this->createFeature($this->dataset['id'], [
      'name'     => 'Inside Feature',
      'geometry' => $this->pointGeometry(3.375, 6.525),
    ]);

    // Feature OUTSIDE the test polygon
    $this->createFeature($this->dataset['id'], [
      'name'     => 'Outside Feature',
      'geometry' => $this->pointGeometry(3.500, 6.700),
    ]);
  }

  /*
    |--------------------------------------------------------------------------
    | ST_Within
    |--------------------------------------------------------------------------
    */

  public function test_within_returns_features_inside_polygon(): void
  {
    $response = $this->postJson('/api/v1/query/within', [
      'dataset_id' => $this->dataset['id'],
      'geometry'   => $this->polygonGeometry(),
    ]);

    $response->assertStatus(200)
      ->assertJsonPath('query', 'within')
      ->assertJsonPath('count', 1);

    $name = $response->json('data.0.name');
    $this->assertEquals('Inside Feature', $name);
  }

  public function test_within_returns_empty_when_no_features_inside(): void
  {
    $response = $this->postJson('/api/v1/query/within', [
      'dataset_id' => $this->dataset['id'],
      'geometry'   => [
        'type'        => 'Polygon',
        'coordinates' => [[
          [0.0, 0.0],
          [0.1, 0.0],
          [0.1, 0.1],
          [0.0, 0.1],
          [0.0, 0.0],
        ]],
      ],
    ]);

    $response->assertStatus(200)
      ->assertJsonPath('count', 0);
  }

  public function test_within_validates_geometry_type(): void
  {
    $response = $this->postJson('/api/v1/query/within', [
      'dataset_id' => $this->dataset['id'],
      'geometry'   => $this->pointGeometry(), // Point not allowed
    ]);

    $response->assertStatus(422);
  }

  /*
    |--------------------------------------------------------------------------
    | Radius (ST_DWithin)
    |--------------------------------------------------------------------------
    */

  public function test_radius_returns_features_within_distance(): void
  {
    // Center point near the 'Inside Feature' at [3.375, 6.525]
    $response = $this->getJson(
      '/api/v1/query/radius?' . http_build_query([
        'dataset_id' => $this->dataset['id'],
        'lat'        => 6.525,
        'lng'        => 3.375,
        'radius'     => 1000, // 1km
      ])
    );

    $response->assertStatus(200)
      ->assertJsonPath('query', 'radius');

    // Should find at least the inside feature
    $this->assertGreaterThanOrEqual(1, $response->json('count'));
  }

  public function test_radius_results_include_distance_meters(): void
  {
    $response = $this->getJson(
      '/api/v1/query/radius?' . http_build_query([
        'dataset_id' => $this->dataset['id'],
        'lat'        => 6.525,
        'lng'        => 3.375,
        'radius'     => 50000,
      ])
    );

    $response->assertStatus(200);

    $firstFeature = $response->json('data.0');
    $this->assertArrayHasKey('distance_meters', $firstFeature);
    $this->assertIsNumeric($firstFeature['distance_meters']);
  }

  public function test_radius_validates_max_radius(): void
  {
    $response = $this->getJson(
      '/api/v1/query/radius?' . http_build_query([
        'dataset_id' => $this->dataset['id'],
        'lat'        => 6.525,
        'lng'        => 3.375,
        'radius'     => 999999, // exceeds 50km max
      ])
    );

    $response->assertStatus(400);
  }

  public function test_radius_validates_coordinate_bounds(): void
  {
    $response = $this->getJson(
      '/api/v1/query/radius?' . http_build_query([
        'dataset_id' => $this->dataset['id'],
        'lat'        => 999,   // invalid
        'lng'        => 3.375,
        'radius'     => 1000,
      ])
    );

    $response->assertStatus(422);
  }

  /*
    |--------------------------------------------------------------------------
    | Nearest
    |--------------------------------------------------------------------------
    */

  public function test_nearest_returns_closest_features(): void
  {
    $response = $this->getJson(
      '/api/v1/query/nearest?' . http_build_query([
        'dataset_id' => $this->dataset['id'],
        'lat'        => 6.525,
        'lng'        => 3.375,
        'limit'      => 1,
      ])
    );

    $response->assertStatus(200)
      ->assertJsonPath('query', 'nearest')
      ->assertJsonPath('count', 1);

    // Nearest to [3.375, 6.525] should be 'Inside Feature'
    $this->assertEquals('Inside Feature', $response->json('data.0.name'));
  }

  public function test_nearest_results_are_ordered_by_distance(): void
  {
    $response = $this->getJson(
      '/api/v1/query/nearest?' . http_build_query([
        'dataset_id' => $this->dataset['id'],
        'lat'        => 6.525,
        'lng'        => 3.375,
        'limit'      => 10,
      ])
    );

    $response->assertStatus(200);

    $distances = array_column($response->json('data'), 'distance_meters');

    // Verify ascending order
    for ($i = 1; $i < count($distances); $i++) {
      $this->assertGreaterThanOrEqual(
        $distances[$i - 1],
        $distances[$i],
        'Results should be ordered by distance ascending'
      );
    }
  }

  /*
    |--------------------------------------------------------------------------
    | Intersects (ST_Intersects)
    |--------------------------------------------------------------------------
    */

  public function test_intersects_returns_features_crossing_line(): void
  {
    // Insert a polygon that overlaps with our test linestring
    $dataset = $this->createDataset(['name' => 'Intersect Test']);
    $this->createFeature($dataset['id'], [
      'name'     => 'Overlapping Polygon',
      'geometry' => $this->polygonGeometry(),
    ]);

    $response = $this->postJson('/api/v1/query/intersects', [
      'dataset_id' => $dataset['id'],
      'geometry'   => $this->lineStringGeometry(),
    ]);

    $response->assertStatus(200)
      ->assertJsonPath('query', 'intersects');

    $this->assertGreaterThanOrEqual(1, $response->json('count'));
  }

  /*
    |--------------------------------------------------------------------------
    | Dataset Not Found
    |--------------------------------------------------------------------------
    */

  public function test_spatial_query_returns_404_for_missing_dataset(): void
  {
    $response = $this->postJson('/api/v1/query/within', [
      'dataset_id' => '00000000-0000-0000-0000-000000000000',
      'geometry'   => $this->polygonGeometry(),
    ]);

    $response->assertStatus(404);
  }
}
