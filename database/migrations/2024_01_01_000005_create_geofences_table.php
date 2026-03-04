<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  public function up(): void
  {
    DB::statement('
            CREATE TABLE geofences (
                id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                name         VARCHAR(255) NOT NULL,
                type         VARCHAR(50)  NOT NULL DEFAULT \'restriction\'
                                 CHECK (type IN (\'restriction\',\'advisory\',\'survey\',\'custom\')),
                geometry     GEOMETRY(Polygon, 4326) NOT NULL,
                altitude_min DOUBLE PRECISION,
                altitude_max DOUBLE PRECISION,
                active       BOOLEAN      NOT NULL DEFAULT TRUE,
                metadata     JSONB        NOT NULL DEFAULT \'{}\'::jsonb,
                created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
        ');

    DB::statement('
            CREATE INDEX idx_geofences_geometry
            ON geofences
            USING GIST (geometry)
        ');

    DB::statement('CREATE INDEX idx_geofences_active ON geofences (active)');
    DB::statement('CREATE INDEX idx_geofences_type   ON geofences (type)');

    // Geofence events — produced by the evaluator
    DB::statement('
            CREATE TABLE geofence_events (
                id          BIGSERIAL    PRIMARY KEY,
                geofence_id UUID         NOT NULL REFERENCES geofences(id) ON DELETE CASCADE,
                mission_id  UUID         REFERENCES missions(id) ON DELETE SET NULL,
                event_type  VARCHAR(20)  NOT NULL CHECK (event_type IN (\'enter\',\'exit\')),
                position    GEOMETRY(Point, 4326) NOT NULL,
                recorded_at TIMESTAMPTZ  NOT NULL,
                created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
        ');

    DB::statement('CREATE INDEX idx_gf_events_geofence   ON geofence_events (geofence_id)');
    DB::statement('CREATE INDEX idx_gf_events_mission    ON geofence_events (mission_id)');
    DB::statement('CREATE INDEX idx_gf_events_type       ON geofence_events (event_type)');
    DB::statement('CREATE INDEX idx_gf_events_recorded   ON geofence_events (recorded_at)');
  }

  public function down(): void
  {
    DB::statement('DROP TABLE IF EXISTS geofence_events CASCADE');
    DB::statement('DROP TABLE IF EXISTS geofences CASCADE');
  }
};
