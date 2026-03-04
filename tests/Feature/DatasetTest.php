<?php

// tests/Feature/DatasetTest.php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatasetTest extends TestCase
{
  use RefreshDatabase;

  /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

  public function test_can_create_a_dataset(): void
  {
    $response = $this->postJson('/api/v1/datasets', [
      'name'        => 'Lagos Infrastructure',
      'type'        => 'infrastructure',
      'description' => 'Infrastructure dataset for Lagos',
    ]);

    $response->assertStatus(201)
      ->assertJsonStructure([
        'data' => [
          'id',
          'name',
          'slug',
          'type',
          'description',
          'srid',
          'created_at',
        ],
      ])
      ->assertJsonPath('data.name', 'Lagos Infrastructure')
      ->assertJsonPath('data.type', 'infrastructure')
      ->assertJsonPath('data.srid', 4326);

    $this->assertDatabaseHas('datasets', [
      'name' => 'Lagos Infrastructure',
      'type' => 'infrastructure',
    ]);
  }

  public function test_create_dataset_generates_slug(): void
  {
    $response = $this->postJson('/api/v1/datasets', [
      'name' => 'My Test Dataset',
      'type' => 'locations',
    ]);

    $response->assertStatus(201);

    $slug = $response->json('data.slug');
    $this->assertStringStartsWith('my-test-dataset', $slug);
  }

  public function test_create_dataset_validates_required_fields(): void
  {
    $response = $this->postJson('/api/v1/datasets', []);

    $response->assertStatus(422)
      ->assertJsonPath('error', 'Validation failed.')
      ->assertJsonStructure(['errors' => ['name', 'type']]);
  }

  public function test_create_dataset_validates_type(): void
  {
    $response = $this->postJson('/api/v1/datasets', [
      'name' => 'Test',
      'type' => 'invalid_type',
    ]);

    $response->assertStatus(422)
      ->assertJsonStructure(['errors' => ['type']]);
  }

  /*
    |--------------------------------------------------------------------------
    | Read
    |--------------------------------------------------------------------------
    */

  public function test_can_list_datasets(): void
  {
    $this->createDataset(['name' => 'Dataset A']);
    $this->createDataset(['name' => 'Dataset B']);

    $response = $this->getJson('/api/v1/datasets');

    $response->assertStatus(200)
      ->assertJsonStructure([
        'data'  => [['id', 'name', 'slug', 'type']],
        'links' => ['first', 'last'],
        'meta'  => ['current_page', 'total'],
      ]);

    $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
  }

  public function test_can_get_single_dataset(): void
  {
    $dataset  = $this->createDataset(['name' => 'Survey Zone Alpha']);
    $response = $this->getJson("/api/v1/datasets/{$dataset['id']}");

    $response->assertStatus(200)
      ->assertJsonPath('data.id',   $dataset['id'])
      ->assertJsonPath('data.name', 'Survey Zone Alpha');
  }

  public function test_returns_404_for_missing_dataset(): void
  {
    $response = $this->getJson('/api/v1/datasets/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404)
      ->assertJsonPath('error', 'Resource not found.');
  }

  /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

  public function test_can_update_a_dataset(): void
  {
    $dataset  = $this->createDataset();
    $response = $this->putJson("/api/v1/datasets/{$dataset['id']}", [
      'description' => 'Updated description',
    ]);

    $response->assertStatus(200)
      ->assertJsonPath('data.description', 'Updated description');

    $this->assertDatabaseHas('datasets', [
      'id'          => $dataset['id'],
      'description' => 'Updated description',
    ]);
  }

  /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

  public function test_can_delete_a_dataset(): void
  {
    $dataset  = $this->createDataset();
    $response = $this->deleteJson("/api/v1/datasets/{$dataset['id']}");

    $response->assertStatus(200)
      ->assertJsonPath('message', 'Dataset deleted successfully.');

    $this->assertDatabaseMissing('datasets', ['id' => $dataset['id']]);
  }

  /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

  public function test_creating_dataset_writes_audit_log(): void
  {
    $dataset = $this->createDataset(['name' => 'Audited Dataset']);

    $this->assertDatabaseHas('audit_logs', [
      'entity_type' => 'dataset',
      'entity_id'   => $dataset['id'],
      'action'      => 'created',
    ]);
  }
}
