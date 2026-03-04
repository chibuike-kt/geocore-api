<?php

// tests/Feature/MissionTest.php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MissionTest extends TestCase
{
  use RefreshDatabase;

  /*
    |--------------------------------------------------------------------------
    | Mission CRUD
    |--------------------------------------------------------------------------
    */

  public function test_can_create_a_mission(): void
  {
    $response = $this->postJson('/api/v1/missions', [
      'name'     => 'Lagos Harbour Survey',
      'operator' => 'Alpha Team',
    ]);

    $response->assertStatus(201)
      ->assertJsonPath('data.name',     'Lagos Harbour Survey')
      ->assertJsonPath('data.operator', 'Alpha Team')
      ->assertJsonPath('data.status',   'planned');

    $this->assertDatabaseHas('missions', [
      'name'   => 'Lagos Harbour Survey',
      'status' => 'planned',
    ]);
  }

  public function test_can_create_mission_with_planned_area(): void
  {
    $response = $this->postJson('/api/v1/missions', [
      'name'         => 'Survey Mission',
      'operator'     => 'Beta Team',
      'planned_area' => $this->polygonGeometry(),
    ]);

    $response->assertStatus(201);

    // Planned area should come back as GeoJSON geometry
    $this->assertNotNull($response->json('data.planned_area'));
    $this->assertEquals('Polygon', $response->json('data.planned_area.type'));
  }

  public function test_can_list_missions(): void
  {
    $this->createMission(['name' => 'Mission Alpha']);
    $this->createMission(['name' => 'Mission Beta']);

    $response = $this->getJson('/api/v1/missions');

    $response->assertStatus(200);
    $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
  }

  public function test_can_get_single_mission(): void
  {
    $mission  = $this->createMission(['name' => 'Solo Mission']);
    $response = $this->getJson("/api/v1/missions/{$mission['id']}");

    $response->assertStatus(200)
      ->assertJsonPath('data.id',   $mission['id'])
      ->assertJsonPath('data.name', 'Solo Mission');
  }

  /*
    |--------------------------------------------------------------------------
    | Status Transitions
    |--------------------------------------------------------------------------
    */

  public function test_can_transition_mission_to_active(): void
  {
    $mission  = $this->createMission();
    $response = $this->patchJson("/api/v1/missions/{$mission['id']}/status", [
      'status' => 'active',
    ]);

    $response->assertStatus(200)
      ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('missions', [
      'id'     => $mission['id'],
      'status' => 'active',
    ]);
  }

  public function test_can_complete_an_active_mission(): void
  {
    $mission = $this->createMission();
    $this->activateMission($mission['id']);

    $response = $this->patchJson("/api/v1/missions/{$mission['id']}/status", [
      'status' => 'completed',
    ]);

    $response->assertStatus(200)
      ->assertJsonPath('data.status', 'completed');
  }

  public function test_cannot_skip_status_transition(): void
  {
    $mission = $this->createMission(); // planned

    // Cannot jump from planned → completed
    $response = $this->patchJson("/api/v1/missions/{$mission['id']}/status", [
      'status' => 'completed',
    ]);

    $response->assertStatus(400);
  }

  public function test_cannot_transition_completed_mission(): void
  {
    $mission = $this->createMission();
    $this->activateMission($mission['id']);

    $this->patchJson("/api/v1/missions/{$mission['id']}/status", [
      'status' => 'completed',
    ]);

    // Try to reactivate a completed mission
    $response = $this->patchJson("/api/v1/missions/{$mission['id']}/status", [
      'status' => 'active',
    ]);

    $response->assertStatus(400);
  }

  /*
    |--------------------------------------------------------------------------
    | Telemetry Ingestion
    |--------------------------------------------------------------------------
    */

  public function test_can_ingest_single_telemetry_ping(): void
  {
    $mission = $this->createMission();
    $this->activateMission($mission['id']);

    $response = $this->postJson("/api/v1/missions/{$mission['id']}/telemetry", [
      'recorded_at' => '2026-03-04T10:00:00Z',
      'lat'         => 6.430,
      'lng'         => 3.375,
      'altitude'    => 120.5,
      'speed'       => 12.3,
      'heading'     => 180.0,
    ]);

    $response->assertStatus(201)
      ->assertJsonPath('message', 'Telemetry ping recorded.')
      ->assertJsonPath('mission_id', $mission['id']);

    $this->assertDatabaseHas('telemetry', [
      'mission_id' => $mission['id'],
      'altitude'   => 120.5,
    ]);
  }

  public function test_can_ingest_telemetry_batch(): void
  {
    $mission = $this->createMission();
    $this->activateMission($mission['id']);

    $response = $this->postJson("/api/v1/missions/{$mission['id']}/telemetry", [
      'pings' => [
        ['recorded_at' => '2026-03-04T10:00:00Z', 'lat' => 6.430, 'lng' => 3.375, 'altitude' => 120],
        ['recorded_at' => '2026-03-04T10:00:05Z', 'lat' => 6.432, 'lng' => 3.378, 'altitude' => 122],
        ['recorded_at' => '2026-03-04T10:00:10Z', 'lat' => 6.435, 'lng' => 3.381, 'altitude' => 121],
      ],
    ]);

    $response->assertStatus(201)
      ->assertJsonPath('inserted', 3);

    $this->assertDatabaseCount('telemetry', 3);
  }

  public function test_telemetry_rejected_for_inactive_mission(): void
  {
    $mission = $this->createMission(); // stays planned

    $response = $this->postJson("/api/v1/missions/{$mission['id']}/telemetry", [
      'recorded_at' => '2026-03-04T10:00:00Z',
      'lat'         => 6.430,
      'lng'         => 3.375,
    ]);

    $response->assertStatus(400);
  }

  /*
    |--------------------------------------------------------------------------
    | Track
    |--------------------------------------------------------------------------
    */

  public function test_track_returns_linestring_from_pings(): void
  {
    $mission = $this->createMission();
    $this->activateMission($mission['id']);

    // Insert 3 pings
    $this->postJson("/api/v1/missions/{$mission['id']}/telemetry", [
      'pings' => [
        ['recorded_at' => '2026-03-04T10:00:00Z', 'lat' => 6.430, 'lng' => 3.375, 'altitude' => 120],
        ['recorded_at' => '2026-03-04T10:00:05Z', 'lat' => 6.432, 'lng' => 3.378, 'altitude' => 122],
        ['recorded_at' => '2026-03-04T10:00:10Z', 'lat' => 6.435, 'lng' => 3.381, 'altitude' => 121],
      ],
    ]);

    $response = $this->getJson("/api/v1/missions/{$mission['id']}/track");

    $response->assertStatus(200)
      ->assertJsonPath('mission_id',  $mission['id'])
      ->assertJsonPath('point_count', 3);

    $this->assertEquals('LineString', $response->json('track.geometry.type'));
    $this->assertCount(3, $response->json('track.geometry.coordinates'));
  }

  public function test_track_returns_null_geometry_for_single_ping(): void
  {
    $mission = $this->createMission();
    $this->activateMission($mission['id']);

    $this->postJson("/api/v1/missions/{$mission['id']}/telemetry", [
      'recorded_at' => '2026-03-04T10:00:00Z',
      'lat'         => 6.430,
      'lng'         => 3.375,
    ]);

    $response = $this->getJson("/api/v1/missions/{$mission['id']}/track");

    $response->assertStatus(200)
      ->assertJsonPath('track', null);
  }

  public function test_track_includes_flight_statistics(): void
  {
    $mission = $this->createMission();
    $this->activateMission($mission['id']);

    $this->postJson("/api/v1/missions/{$mission['id']}/telemetry", [
      'pings' => [
        ['recorded_at' => '2026-03-04T10:00:00Z', 'lat' => 6.430, 'lng' => 3.375, 'altitude' => 100, 'speed' => 10],
        ['recorded_at' => '2026-03-04T10:00:05Z', 'lat' => 6.435, 'lng' => 3.380, 'altitude' => 200, 'speed' => 20],
      ],
    ]);

    $response = $this->getJson("/api/v1/missions/{$mission['id']}/track");

    $response->assertStatus(200)
      ->assertJsonStructure(['stats' => [
        'min_altitude',
        'max_altitude',
        'avg_speed',
        'started_at',
        'ended_at',
      ]]);

    $this->assertEquals(100, $response->json('stats.min_altitude'));
    $this->assertEquals(200, $response->json('stats.max_altitude'));
  }
}
